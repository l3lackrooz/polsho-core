<?php

namespace App\Domain\Market\Infrastructure\Notifications;

use App\Domain\Market\Application\DTO\AggregatedQuoteDTO;
use App\Domain\Market\Application\DTO\QuoteDTO;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketSnapshot;
use App\Services\BotMessaging\BotMessagingManager;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class BalePriceAlertService
{
    private const CACHE_TTL_HOURS = 24;
    private const MIN_MOVE_PERCENT = 0.005;
    private const MIN_INTERVAL_MINUTES = 10;
    private const FORCE_INTERVAL_MINUTES = 60;
    private const IRR_MIN_MOVE = 1000.0;
    private const IRT_MIN_MOVE = 100.0;

    public function __construct(
        private readonly BotMessagingManager $bots,
        private readonly CacheRepository $cache,
        private readonly ConfigRepository $config,
        private readonly BalePriceAlertFormatter $formatter,
    ) {}

    public function sendFor(AggregatedQuoteDTO $aggregated): bool
    {
        Log::info("Bale Start");
        $targets = $this->resolveTargets();
//Log::info("Bale Target", [$targets]);
        if ($targets === []) {
            return false;
        }

        $prices = $this->resolveProviderPrices($aggregated);
//Log::info("Bale Prices",[$prices]);
        if ($prices === []) {
            return false;
        }

        $price = $this->resolveReferencePrice($prices);
//Log::info("Bale Price", [$price]);
        if ($price <= 0.0) {
            return false;
        }

        $instrument = strtoupper($aggregated->instrument);
        $now = now();

        $providerChanges24h = $this->resolveProviderChanges24h($aggregated, $prices);
        $change24h = $this->resolveSummaryChange($providerChanges24h);
        $change24hBucket = $this->resolve24hBucket($change24h);
        $state = $this->getState($this->cacheKey($instrument));
//Log::info("Bale ShouldNotify?");
//Log::info("Bale State", $state);
//Log::info("Bale Bucket", [$change24hBucket]);
//Log::info("Notify Debug", [
//     'price' => $price,
//     'last_price' => $state['last_price'],
//     'price_diff' => $state['last_price'] ? $price - $state['last_price'] : null,
//     'bucket' => $change24hBucket,
//     'state_bucket' => $state['last_24h_bucket'],
//     'minutes_since_last' => $state['last_sent_at']?->diffInMinutes($now),
// ]);
        if (! $this->shouldNotify($instrument, $price, $change24hBucket, $state, $now)) {
            return false;
        }
//Log::info("Bale Should Notify True!!!!!====");
        $message = $this->formatter->format($aggregated, $providerChanges24h);

        try {
            $this->bots->broadcastMessage($targets, $message);
        } catch (Throwable $exception) {
            Log::channel('bot')->error('Market price alert failed', [
                'instrument' => $instrument,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        $this->storeState(
            cacheKey: $this->cacheKey($instrument),
            price: $price,
            bucket: $change24hBucket,
            sentAt: $now,
        );

        return true;
    }

    /**
     * @return array<int, array{platform: string, chat_id: string}>
     */
    private function resolveTargets(): array
    {
        $targets = [];

        foreach (['bale', 'telegram'] as $platform) {
            $chatId = $this->config->get(sprintf('services.%s_bot.chat_id', $platform));

            if (! is_scalar($chatId) || (string) $chatId === '') {
                continue;
            }

            $targets[] = [
                'platform' => $platform,
                'chat_id' => (string) $chatId,
            ];
        }

        return $targets;
    }

    /**
     * @return array<string, float>
     */
    private function resolveProviderPrices(AggregatedQuoteDTO $aggregated): array
    {
        $prices = [];

        foreach ($aggregated->providers as $quote) {
            $price = $this->resolvePrice($quote, $aggregated);

            if ($price > 0.0) {
                $prices[$quote->provider] = $price;
            }
        }

        return $prices;
    }

    private function resolvePrice(QuoteDTO $quote, AggregatedQuoteDTO $aggregated): float
    {
        return $quote->last
            ?? $quote->mid()
            ?? $aggregated->bestAsk?->ask
            ?? $aggregated->bestBid?->bid
            ?? 0.0;
    }

    /**
     * @param array<string, float> $prices
     * @return array<string, float|null>
     */
    private function resolveProviderChanges24h(AggregatedQuoteDTO $aggregated, array $prices): array
    {
        $changes = [];

        foreach ($aggregated->providers as $quote) {
            $changes[$quote->provider] = $this->calculate24hChange(
                $quote,
                $prices[$quote->provider] ?? $this->resolvePrice($quote, $aggregated),
            );
        }

        return $changes;
    }

    /**
     * @param array<string, float> $prices
     */
    private function resolveReferencePrice(array $prices): float
    {
        if ($prices === []) {
            return 0.0;
        }

        sort($prices);
        $count = count($prices);
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return $prices[$middle];
        }

        return ($prices[$middle - 1] + $prices[$middle]) / 2;
    }

    private function calculate24hChange(QuoteDTO $quote, float $currentPrice): ?float
    {
        if ($quote->providerMarketId === null) {
            return null;
        }

        /** @var MarketSnapshot|null $snapshot */
        $snapshot = MarketSnapshot::query()
            ->where('provider_market_id', $quote->providerMarketId)
            ->where('captured_at', '<=', now()->subDay())
            ->whereNotNull('last_price')
            ->latest('captured_at')
            ->first();

        if ($snapshot === null) {
            return null;
        }

        $previousPrice = (float) $snapshot->last_price;

        if ($previousPrice <= 0.0) {
            return null;
        }

        return (($currentPrice - $previousPrice) / $previousPrice) * 100;
    }

    /**
     * @param array<string, float|null> $providerChanges24h
     */
    private function resolveSummaryChange(array $providerChanges24h): ?float
    {
        $changes = array_values(array_filter($providerChanges24h, static fn (?float $change): bool => $change !== null));

        if ($changes === []) {
            return null;
        }

        return array_sum($changes) / count($changes);
    }

    /**
     * @param array{last_price: float|null, last_sent_at: Carbon|null, last_24h_bucket: string|null} $state
     */
    private function shouldNotify(
        string $instrument,
        float $price,
        ?string $change24hBucket,
        array $state,
        Carbon $now,
    ): bool {
        if ($state['last_price'] === null) {
            return true;
        }

        if (
            $change24hBucket !== null
            && $change24hBucket !== $state['last_24h_bucket']
            && $this->intervalElapsed($state['last_sent_at'], $now, self::MIN_INTERVAL_MINUTES)
        ) {
            return true;
        }

        if (
            $this->hasMeaningfulPriceMove($instrument, $price, (float) $state['last_price'])
            && $this->intervalElapsed($state['last_sent_at'], $now, self::MIN_INTERVAL_MINUTES)
        ) {
            return true;
        }

        return $this->intervalElapsed($state['last_sent_at'], $now, self::FORCE_INTERVAL_MINUTES);
    }

    private function hasMeaningfulPriceMove(string $instrument, float $price, float $lastPrice): bool
    {
        if ($lastPrice <= 0.0) {
            return true;
        }

        $absoluteChange = abs($price - $lastPrice);
        $quoteCurrency = $this->quoteCurrency($instrument);
        $absoluteThreshold = match ($quoteCurrency) {
            'IRR' => self::IRR_MIN_MOVE,
            'IRT' => self::IRT_MIN_MOVE,
            default => null,
        };

        if ($absoluteThreshold !== null) {
            return $absoluteChange >= $absoluteThreshold;
        }

        $percentChange = ($absoluteChange / $lastPrice) * 100;

        return $percentChange >= self::MIN_MOVE_PERCENT;
    }

    private function intervalElapsed(?Carbon $lastSentAt, Carbon $now, int $minutes): bool
    {
        return $lastSentAt === null || $lastSentAt->diffInMinutes($now) >= $minutes;
    }

    /**
     * @return array{last_price: float|null, last_sent_at: Carbon|null, last_24h_bucket: string|null}
     */
    private function getState(string $key): array
    {
        $state = $this->cache->get($key);

        if (! is_array($state)) {
            return [
                'last_price' => null,
                'last_sent_at' => null,
                'last_24h_bucket' => null,
            ];
        }

        $lastSentAt = $state['last_sent_at'] ?? null;

        return [
            'last_price' => isset($state['last_price']) ? (float) $state['last_price'] : null,
            'last_sent_at' => $lastSentAt instanceof Carbon ? $lastSentAt : ($lastSentAt ? Carbon::parse($lastSentAt) : null),
            'last_24h_bucket' => isset($state['last_24h_bucket']) ? (string) $state['last_24h_bucket'] : null,
        ];
    }

    private function storeState(string $cacheKey, float $price, ?string $bucket, Carbon $sentAt): void
    {
        $this->cache->put(
            $cacheKey,
            [
                'last_price' => $price,
                'last_sent_at' => $sentAt->toIso8601String(),
                'last_24h_bucket' => $bucket,
            ],
            $sentAt->copy()->addHours(self::CACHE_TTL_HOURS),
        );
    }

    private function cacheKey(string $instrument): string
    {
        return sprintf('market.notification.bale.%s', $instrument);
    }

    private function resolve24hBucket(?float $change): ?string
    {
        return match (true) {
            $change === null => null,
            $change >= 10 => '+10',
            $change >= 5 => '+5',
            $change <= -10 => '-10',
            $change <= -5 => '-5',
            default => null,
        };
    }

    private function quoteCurrency(string $instrument): ?string
    {
        [, $quote] = array_pad(explode('-', strtoupper($instrument), 2), 2, null);

        return $quote;
    }
}
