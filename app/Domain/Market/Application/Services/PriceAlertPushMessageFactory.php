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
        $body = $price === null
            ? sprintf('%s reached your target.', $alert->instrument->symbol)
            : sprintf('%s reached %s.', $alert->instrument->symbol, number_format($price, 8, '.', ','));

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
        );
    }
}
