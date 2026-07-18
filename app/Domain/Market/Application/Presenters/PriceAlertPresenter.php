<?php

namespace App\Domain\Market\Application\Presenters;

use App\Domain\Market\Infrastructure\Persistence\Models\PriceAlert;
use App\Domain\Market\Infrastructure\Stores\AggregateStore;
use Throwable;

/**
 * Shapes a price alert for client applications without coupling them to the
 * persistence model or forcing them to issue a second request for its price.
 */
class PriceAlertPresenter
{
    public function __construct(private readonly AggregateStore $aggregates) {}

    /** @return array<string, mixed> */
    public function present(PriceAlert $alert): array
    {
        $instrument = $alert->instrument;
        $providerMarket = $alert->providerMarket;

        return [
            'id' => $alert->id,
            'instrument' => [
                'id' => $instrument->id,
                'symbol' => $instrument->symbol,
                'base_asset' => [
                    'symbol' => $instrument->baseAsset->symbol,
                    'name' => $instrument->baseAsset->name,
                ],
                'quote_asset' => [
                    'symbol' => $instrument->quoteAsset->symbol,
                    'name' => $instrument->quoteAsset->name,
                ],
            ],
            'provider_market' => $providerMarket === null ? null : [
                'id' => $providerMarket->id,
                'remote_symbol' => $providerMarket->remote_symbol,
                'provider' => [
                    'name' => $providerMarket->provider->name,
                    'slug' => $providerMarket->provider->slug,
                ],
            ],
            'scope' => $alert->scope,
            'condition' => $alert->condition,
            'target_price' => $alert->target_price,
            'baseline_price' => $alert->baseline_price,
            'current_price' => $this->currentPrice($alert),
            'status' => $alert->status,
            'repeat' => $alert->repeat,
            'notify_push' => $alert->notify_push,
            'notify_in_app' => $alert->notify_in_app,
            'last_triggered_at' => $alert->last_triggered_at?->toISOString(),
            'expires_at' => $alert->expires_at?->toISOString(),
            'created_at' => $alert->created_at->toISOString(),
            'updated_at' => $alert->updated_at->toISOString(),
            'events' => $alert->events->map(static fn ($event): array => [
                'type' => $event->type,
                'payload' => $event->payload,
                'occurred_at' => $event->occurred_at->toISOString(),
            ])->values()->all(),
        ];
    }

    public function currentPrice(PriceAlert $alert): ?float
    {
        try {
            $aggregate = $this->aggregates->get($alert->instrument->symbol);
        } catch (Throwable) {
            // The alert remains useful even while the transient quote cache is unavailable.
            return null;
        }

        if ($aggregate === null) {
            return null;
        }

        if ($alert->scope === 'specific_exchange') {
            foreach ([...($aggregate['providers'] ?? []), ...($aggregate['comparison_providers'] ?? [])] as $quote) {
                if ((int) ($quote['provider_market_id'] ?? 0) === (int) $alert->provider_market_id) {
                    return $this->priceFromQuote($quote);
                }
            }

            return null;
        }

        return $this->priceFromQuote($aggregate['best_ask'] ?? null)
            ?? $this->priceFromQuote($aggregate['best_bid'] ?? null);
    }

    /** @param array<string, mixed>|null $quote */
    private function priceFromQuote(?array $quote): ?float
    {
        if ($quote === null) {
            return null;
        }

        foreach (['last', 'mid', 'ask', 'bid'] as $key) {
            $value = $quote[$key] ?? null;
            if (is_numeric($value) && (float) $value > 0) {
                return (float) $value;
            }
        }

        return null;
    }
}
