<?php

namespace App\Domain\Market\Application\Presenters;

use Illuminate\Notifications\DatabaseNotification;

class MarketNotificationPresenter
{
    /** @return array<string, mixed> */
    public function present(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];

        return [
            'id' => (string) $notification->getKey(),
            'type' => (string) ($data['type'] ?? 'system'),
            'title' => (string) ($data['title'] ?? 'Notification'),
            'body' => (string) ($data['body'] ?? ''),
            'price_alert_id' => isset($data['price_alert_id']) ? (string) $data['price_alert_id'] : null,
            'instrument' => isset($data['instrument']) ? (string) $data['instrument'] : null,
            'price' => is_numeric($data['price'] ?? null) ? (float) $data['price'] : null,
            'target_price' => is_numeric($data['target_price'] ?? null) ? (float) $data['target_price'] : null,
            'provider' => isset($data['provider']) ? (string) $data['provider'] : null,
            'created_at' => $notification->created_at?->toISOString(),
            'read_at' => $notification->read_at?->toISOString(),
        ];
    }
}
