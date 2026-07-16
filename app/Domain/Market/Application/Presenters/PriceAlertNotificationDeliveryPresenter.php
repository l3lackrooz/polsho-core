<?php

namespace App\Domain\Market\Application\Presenters;

use App\Domain\Market\Infrastructure\Persistence\Models\PriceAlertNotificationDelivery;

class PriceAlertNotificationDeliveryPresenter
{
    /** @return array<string, mixed> */
    public function present(PriceAlertNotificationDelivery $delivery): array
    {
        $alert = $delivery->event->alert;
        $user = $alert->user;

        return [
            'id' => $delivery->id,
            'price_alert_event_id' => $delivery->price_alert_event_id,
            'price_alert_id' => $alert->id,
            'instrument' => $alert->instrument->symbol,
            'user' => [
                'id' => $user?->id,
                'name' => $user?->name,
                'email' => $user?->email,
            ],
            'provider' => $delivery->provider,
            'in_app_sent_at' => $delivery->in_app_sent_at?->toISOString(),
            'push_status' => $delivery->push_status,
            'push_attempts' => $delivery->push_attempts,
            'provider_message_id' => $delivery->provider_message_id,
            'push_error' => $delivery->push_error,
            'push_sent_at' => $delivery->push_sent_at?->toISOString(),
            'targets' => $delivery->pushDeliveries->map(static fn ($target): array => [
                'id' => $target->id,
                'push_device_id' => $target->push_device_id,
                'installation_id' => $target->pushDevice?->installation_id,
                'platform' => $target->platform,
                'provider' => $target->provider,
                'status' => $target->status,
                'attempts' => $target->attempts,
                'provider_message_id' => $target->provider_message_id,
                'error' => $target->error,
                'sent_at' => $target->sent_at?->toISOString(),
            ])->values()->all(),
            'created_at' => $delivery->created_at?->toISOString(),
            'updated_at' => $delivery->updated_at?->toISOString(),
        ];
    }
}
