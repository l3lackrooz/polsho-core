<?php

namespace App\Domain\Market\Infrastructure\Notifications;

use App\Domain\Market\Infrastructure\Persistence\Models\PriceAlertEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PriceAlertTriggeredNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly PriceAlertEvent $event) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $alert = $this->event->alert;
        $payload = is_array($this->event->payload) ? $this->event->payload : [];
        $price = is_numeric($payload['price'] ?? null) ? (float) $payload['price'] : null;

        return [
            'type' => 'price_alert.triggered',
            'price_alert_id' => $alert->id,
            'price_alert_event_id' => $this->event->id,
            'instrument' => $alert->instrument->symbol,
            'price' => $price,
            'target_price' => (float) $alert->target_price,
            'provider' => $payload['provider'] ?? null,
            'is_reference' => (bool) ($payload['is_reference'] ?? false),
            'title' => 'Price alert triggered',
            'body' => $this->body($alert->instrument->symbol, $price),
        ];
    }

    private function body(string $instrument, ?float $price): string
    {
        return $price === null
            ? sprintf('%s reached your target.', $instrument)
            : sprintf('%s reached %s.', $instrument, number_format($price, 8, '.', ','));
    }
}
