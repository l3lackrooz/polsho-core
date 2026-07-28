<?php

namespace App\Domain\Market\Infrastructure\Support\Utility;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Application\DTO\QuoteDTO;

class ProviderQuoteFactory
{
    /**
     * Currency aliases that may be embedded in a provider's remote symbol.
     *
     * @var array<string, string>
     */
    private const REMOTE_CURRENCY_ALIASES = [
        'TOMAN' => 'IRT',
        'RIAL' => 'IRR',
        'IRR' => 'IRR',
        'IRT' => 'IRT',
        'RLS' => 'IRR',
        'TMN' => 'IRT',
    ];

    public function __construct(
        private readonly CurrencyNormalizer $currencies = new CurrencyNormalizer,
    ) {}

    public function make(
        MarketSubscriptionDTO $subscription,
        float $bid,
        float $ask,
        ?float $last,
        string $provider,
        ?float $volume,
        int $timestamp,
        bool $isReference = false,
    ): QuoteDTO {
        [$sourceBase, $sourceQuote] = $this->sourceCurrencies($subscription);

        return new QuoteDTO(
            instrument: $subscription->instrument,
            bid: $this->normalizePrice($bid, $sourceBase, $sourceQuote, $subscription),
            ask: $this->normalizePrice($ask, $sourceBase, $sourceQuote, $subscription),
            last: $last === null
                ? null
                : $this->normalizePrice($last, $sourceBase, $sourceQuote, $subscription),
            provider: $provider,
            volume: $volume === null
                ? null
                : $this->currencies->convertPriceTo(
                    $sourceBase,
                    $subscription->base,
                    $volume,
                ),
            timestamp: $timestamp,
            providerMarketId: $subscription->providerMarketId,
            isReference: $isReference,
        );
    }

    private function normalizePrice(
        float $price,
        string $sourceBase,
        string $sourceQuote,
        MarketSubscriptionDTO $subscription,
    ): float {
        // A market price is quote units per one base unit. Changing the base
        // unit therefore applies the inverse conversion direction.
        $baseFactor = $this->currencies->convertPriceTo(
            $subscription->base,
            $sourceBase,
            1.0,
        );

        return $this->currencies->convertPriceTo(
            $sourceQuote,
            $subscription->quote,
            $price * $baseFactor,
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function sourceCurrencies(MarketSubscriptionDTO $subscription): array
    {
        $sourceBase = $this->metadataCurrency(
            $subscription->metadata,
            ['source_base', 'source_base_currency', 'price_base'],
        );
        $sourceQuote = $this->metadataCurrency(
            $subscription->metadata,
            ['source_quote', 'source_quote_currency', 'price_quote', 'price_currency'],
        );

        [$remoteBase, $remoteQuote] = $this->remoteCurrencies($subscription->remoteSymbol);

        return [
            $sourceBase ?? $remoteBase ?? $subscription->base,
            $sourceQuote ?? $remoteQuote ?? $subscription->quote,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<int, string>  $keys
     */
    private function metadataCurrency(array $metadata, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $metadata[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return $this->currencies->normalize($value);
            }
        }

        return null;
    }

    /**
     * Detects only explicit Iranian currency aliases at either edge of a
     * remote pair. Other currencies stay equal to the canonical subscription.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function remoteCurrencies(string $remoteSymbol): array
    {
        $symbol = strtoupper(trim($remoteSymbol));
        $sourceBase = null;
        $sourceQuote = null;

        foreach (self::REMOTE_CURRENCY_ALIASES as $alias => $currency) {
            if ($sourceBase === null
                && strlen($symbol) > strlen($alias)
                && str_starts_with($symbol, $alias)) {
                $sourceBase = $currency;
            }

            if ($sourceQuote === null
                && strlen($symbol) > strlen($alias)
                && str_ends_with($symbol, $alias)) {
                $sourceQuote = $currency;
            }
        }

        return [$sourceBase, $sourceQuote];
    }
}
