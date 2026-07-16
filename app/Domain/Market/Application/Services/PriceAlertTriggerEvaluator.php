<?php

namespace App\Domain\Market\Application\Services;

use App\Domain\Asset\Models\Instrument;
use App\Domain\Market\Application\DTO\AggregatedQuoteDTO;
use App\Domain\Market\Application\DTO\AlertEvaluationPriceDTO;
use App\Domain\Market\Application\DTO\ComparisonProviderQuoteDTO;
use App\Domain\Market\Application\DTO\QuoteDTO;
use App\Domain\Market\Application\Jobs\SendPriceAlertNotificationJob;
use App\Domain\Market\Infrastructure\Persistence\Models\PriceAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Evaluates active user alerts from the canonical aggregate, never from a
 * provider's raw payload. This keeps stale/outlier filtering consistent with
 * the price shown to clients.
 */
class PriceAlertTriggerEvaluator
{
    private const EPSILON = 0.000000001;

    public function evaluate(AggregatedQuoteDTO $aggregate): int
    {
        $instrumentId = Instrument::query()
            ->whereRaw('UPPER(symbol) = ?', [strtoupper($aggregate->instrument)])
            ->value('id');

        if ($instrumentId === null) {
            return 0;
        }

        $triggered = 0;

        PriceAlert::query()
            ->where('instrument_id', $instrumentId)
            ->where('status', 'active')
            ->orderBy('id')
            ->select('id')
            ->chunkById(100, function ($alerts) use ($aggregate, &$triggered): void {
                foreach ($alerts as $alert) {
                    if ($this->evaluateAlert((int) $alert->id, $aggregate)) {
                        $triggered++;
                    }
                }
            });

        return $triggered;
    }

    private function evaluateAlert(int $alertId, AggregatedQuoteDTO $aggregate): bool
    {
        return DB::transaction(function () use ($alertId, $aggregate): bool {
            $alert = PriceAlert::query()->lockForUpdate()->find($alertId);

            if ($alert === null || $alert->status !== 'active') {
                return false;
            }

            if ($alert->expires_at?->isPast()) {
                $alert->update(['status' => 'expired']);

                return false;
            }

            $quote = $this->resolveQuote($alert, $aggregate);

            if ($quote === null) {
                return false;
            }

            $previousPrice = $this->previousPrice($alert);
            $targetPrice = (float) $alert->target_price;
            $didTrigger = $this->shouldTrigger(
                condition: $alert->condition,
                previousPrice: $previousPrice,
                currentPrice: $quote->price,
                targetPrice: $targetPrice,
            );

            if (! $didTrigger && $previousPrice !== null && $this->compare($previousPrice, $quote->price) === 0) {
                return false;
            }

            $metadata = is_array($alert->metadata) ? $alert->metadata : [];
            $metadata['evaluation'] = [
                'last_price' => $quote->price,
                'provider' => $quote->provider,
                'provider_market_id' => $quote->providerMarketId,
                'is_reference' => $quote->isReference,
                'quote_timestamp' => $quote->timestamp,
            ];
            $alert->metadata = $metadata;

            if (! $didTrigger) {
                $alert->save();

                return false;
            }

            $now = now();
            $alert->last_triggered_at = $now;
            if ($alert->repeat === 'once') {
                $alert->status = 'triggered';
            }
            $alert->save();

            $event = $alert->events()->create([
                'type' => 'triggered',
                'payload' => [
                    'price' => $quote->price,
                    'target_price' => $targetPrice,
                    'provider' => $quote->provider,
                    'provider_market_id' => $quote->providerMarketId,
                    'is_reference' => $quote->isReference,
                    'quote_timestamp' => $quote->timestamp,
                ],
                'occurred_at' => $now,
            ]);

            if ($alert->user_id !== null && ($alert->notify_in_app || $alert->notify_push)) {
                SendPriceAlertNotificationJob::dispatch($event->id)->afterCommit();
            }

            Log::info('Price alert triggered', [
                'price_alert_id' => $alert->id,
                'instrument_id' => $alert->instrument_id,
                'price' => $quote->price,
                'target_price' => $targetPrice,
                'provider' => $quote->provider,
                'provider_market_id' => $quote->providerMarketId,
                'is_reference' => $quote->isReference,
            ]);

            return true;
        });
    }

    private function resolveQuote(PriceAlert $alert, AggregatedQuoteDTO $aggregate): ?AlertEvaluationPriceDTO
    {
        if ($alert->scope === 'specific_exchange') {
            return $this->specificProviderQuote($alert, $aggregate);
        }

        return $this->bestMarketQuote($aggregate);
    }

    private function bestMarketQuote(AggregatedQuoteDTO $aggregate): ?AlertEvaluationPriceDTO
    {
        foreach ([$aggregate->bestAsk, $aggregate->bestBid] as $quote) {
            if ($quote === null || $quote->isReference || ! $this->isUsable($quote->timestamp, false)) {
                continue;
            }

            $price = $this->quotePrice($quote);
            if ($price === null) {
                continue;
            }

            return new AlertEvaluationPriceDTO(
                price: $price,
                provider: $quote->provider,
                providerMarketId: $quote->providerMarketId,
                isReference: false,
                timestamp: $quote->timestamp,
            );
        }

        // A reference rate may render as a best-price fallback for discovery,
        // but it must not fire a tradable best-market alert.
        return null;
    }

    private function specificProviderQuote(PriceAlert $alert, AggregatedQuoteDTO $aggregate): ?AlertEvaluationPriceDTO
    {
        foreach ($aggregate->providers as $quote) {
            if ($quote->providerMarketId !== (int) $alert->provider_market_id) {
                continue;
            }

            if (! $this->isUsable($quote->timestamp, $quote->isReference)) {
                return null;
            }

            $price = $this->quotePrice($quote);
            if ($price === null) {
                return null;
            }

            return new AlertEvaluationPriceDTO(
                price: $price,
                provider: $quote->provider,
                providerMarketId: $quote->providerMarketId,
                isReference: $quote->isReference,
                timestamp: $quote->timestamp,
            );
        }

        foreach ($aggregate->comparisonProviders as $quote) {
            if ($quote->providerMarketId !== (int) $alert->provider_market_id) {
                continue;
            }

            return $this->comparisonProviderPrice($quote);
        }

        return null;
    }

    private function comparisonProviderPrice(ComparisonProviderQuoteDTO $quote): ?AlertEvaluationPriceDTO
    {
        if ($quote->timestamp === null || ! $this->isUsable($quote->timestamp, $quote->isReference)) {
            return null;
        }

        $price = $quote->last ?? $quote->ask ?? $quote->bid;
        if ($price === null || $price <= 0) {
            return null;
        }

        return new AlertEvaluationPriceDTO(
            price: $price,
            provider: $quote->provider,
            providerMarketId: $quote->providerMarketId,
            isReference: $quote->isReference,
            timestamp: $quote->timestamp,
        );
    }

    private function quotePrice(QuoteDTO $quote): ?float
    {
        $price = $quote->last ?? $quote->mid() ?? $quote->ask ?? $quote->bid;

        return $price !== null && $price > 0 ? $price : null;
    }

    private function isUsable(int $timestamp, bool $isReference): bool
    {
        $maxAgeSeconds = (int) config(
            $isReference
                ? 'market.alerts.max_reference_quote_age_seconds'
                : 'market.alerts.max_quote_age_seconds',
        );
        $ageMs = abs(now()->getTimestampMs() - $timestamp);

        return $timestamp > 0 && $ageMs <= max(1, $maxAgeSeconds) * 1_000;
    }

    private function previousPrice(PriceAlert $alert): ?float
    {
        $metadata = is_array($alert->metadata) ? $alert->metadata : [];
        $value = $metadata['evaluation']['last_price'] ?? null;

        return is_numeric($value) && (float) $value > 0 ? (float) $value : null;
    }

    private function shouldTrigger(
        string $condition,
        ?float $previousPrice,
        float $currentPrice,
        float $targetPrice,
    ): bool {
        $current = $this->compare($currentPrice, $targetPrice);
        $previous = $previousPrice === null ? null : $this->compare($previousPrice, $targetPrice);

        return match ($condition) {
            'goes_above' => $previous === null ? $current > 0 : $previous <= 0 && $current > 0,
            'goes_below' => $previous === null ? $current < 0 : $previous >= 0 && $current < 0,
            'reaches' => $previous === null
                ? $current === 0
                : ($previous < 0 && $current >= 0) || ($previous > 0 && $current <= 0),
            default => false,
        };
    }

    private function compare(float $left, float $right): int
    {
        $tolerance = max(abs($left), abs($right), 1.0) * self::EPSILON;

        if (abs($left - $right) <= $tolerance) {
            return 0;
        }

        return $left <=> $right;
    }
}
