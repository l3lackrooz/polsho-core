<?php

namespace App\Domain\Market\Application\Services;

use App\Domain\Market\Application\DTO\PushNotificationMessage;
use App\Domain\Market\Infrastructure\Persistence\Models\PriceAlertEvent;

class PriceAlertPushMessageFactory
{
    public function make(PriceAlertEvent $event): PushNotificationMessage
    {
        $alert = $event->alert;
        $payload = is_array($event->payload) ? $event->payload : [];
        $price = is_numeric($payload['price'] ?? null) ? (float) $payload['price'] : null;
        $targetPrice = (float) $alert->target_price;
        $pair = $alert->instrument->symbol;
        [$baseSymbol, $quoteUnit] = array_pad(explode('/', $pair, 2), 2, 'IRT');
        $condition = match ($alert->condition) {
            'goes_above', 'goes_below' => $alert->condition,
            default => ($price ?? $targetPrice) >= $targetPrice ? 'goes_above' : 'goes_below',
        };
        $timestamp = $event->occurred_at?->getTimestamp() ?? now()->getTimestamp();
        $timestampMilliseconds = $timestamp * 1000;
        $body = $price === null
            ? sprintf('%s reached your target.', $pair)
            : sprintf('%s reached %s.', $pair, number_format($price, 8, '.', ','));

        return new PushNotificationMessage(
            title: 'Price alert triggered',
            body: $body,
            data: [
                'type' => 'price_alert.triggered',
                'price_alert_id' => $alert->id,
                'price_alert_event_id' => $event->id,
                'route' => '/alerts/'.$alert->id,
                'schema_version' => 1,
            ],
            deepLink: 'polsho://alerts/'.$alert->id,
            liveActivityStart: [
                'timestamp' => $timestamp,
                'attributes' => [
                    'alert_id' => (string) $alert->id,
                    'pair' => $pair,
                    'base_symbol' => $baseSymbol,
                    'condition' => $condition,
                    'target_price' => $targetPrice,
                    'start_price' => $alert->baseline_price === null ? null : (float) $alert->baseline_price,
                    'quote_unit' => $quoteUnit,
                ],
                'content_state' => [
                    'current_price' => $price,
                    'progress' => 1,
                    'away_fraction' => 0,
                    'phase' => 'triggered',
                    'freshness' => 'live',
                    'updated_at_ms' => $timestampMilliseconds,
                    'provider' => is_string($payload['provider'] ?? null) ? $payload['provider'] : null,
                    'triggered_at_ms' => $timestampMilliseconds,
                ],
            ],
        );
    }
}
