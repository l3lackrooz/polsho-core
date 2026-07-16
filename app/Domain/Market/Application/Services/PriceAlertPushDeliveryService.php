<?php

namespace App\Domain\Market\Application\Services;

use App\Domain\Market\Application\DTO\PushNotificationTarget;
use App\Domain\Market\Infrastructure\Persistence\Models\PriceAlertNotificationDelivery;
use App\Domain\Market\Infrastructure\Persistence\Models\PriceAlertPushDelivery;
use Throwable;

class PriceAlertPushDeliveryService
{
    public function __construct(
        private readonly PushProviderRegistry $providers,
        private readonly PriceAlertPushMessageFactory $messages,
    ) {}

    public function send(int $deliveryId): void
    {
        $delivery = PriceAlertPushDelivery::query()
            ->with(['notificationDelivery.event.alert.instrument', 'pushDevice'])
            ->find($deliveryId);

        if ($delivery === null || in_array($delivery->status, ['sent', 'skipped'], true)) {
            return;
        }

        $targetAddress = $delivery->provider_target;
        if (! is_string($targetAddress) || $targetAddress === '') {
            $delivery->update(['status' => 'skipped', 'error' => 'The provider target is unavailable.']);
            $this->aggregate($delivery->notification_delivery_id);

            return;
        }

        $delivery->increment('attempts');
        $target = new PushNotificationTarget(
            provider: $delivery->provider,
            platform: $delivery->platform,
            address: $targetAddress,
            pushDeviceId: $delivery->push_device_id,
        );
        $event = $delivery->notificationDelivery->event;
        $result = $this->providers->provider($delivery->provider)->send(
            $target,
            $this->messages->make($event),
        );

        if ($result->invalidTarget && $delivery->pushDevice !== null) {
            $delivery->pushDevice->update([
                'provider_token' => null,
                'token_hash' => null,
                'enabled' => false,
                'invalidated_at' => now(),
            ]);
        }

        $delivery->update([
            'status' => $result->status,
            'provider_message_id' => $result->providerMessageId,
            'error' => $result->error,
            'sent_at' => $result->status === 'sent' ? now() : null,
        ]);
        $this->aggregate($delivery->notification_delivery_id);
    }

    public function markFailed(int $deliveryId, Throwable $exception): void
    {
        $delivery = PriceAlertPushDelivery::query()->find($deliveryId);
        if ($delivery === null || in_array($delivery->status, ['sent', 'skipped'], true)) {
            return;
        }

        $delivery->update([
            'status' => 'failed',
            'error' => mb_substr($exception->getMessage(), 0, 2000),
        ]);
        $this->aggregate($delivery->notification_delivery_id);
    }

    public function aggregate(int $notificationDeliveryId): void
    {
        $parent = PriceAlertNotificationDelivery::query()
            ->with('pushDeliveries')
            ->find($notificationDeliveryId);

        if ($parent === null || $parent->pushDeliveries->isEmpty()) {
            return;
        }

        $children = $parent->pushDeliveries;
        $status = match (true) {
            $children->contains('status', 'pending') => 'pending',
            $children->contains('status', 'failed') => 'failed',
            $children->contains('status', 'sent') => 'sent',
            default => 'skipped',
        };
        $providers = $children->pluck('provider')->unique()->values();
        $messageIds = $children->pluck('provider_message_id')->filter()->values();
        $errors = $children->pluck('error')->filter()->unique()->values();

        $parent->update([
            'provider' => $providers->count() === 1 ? $providers->first() : 'multiple',
            'push_status' => $status,
            'push_attempts' => $children->sum('attempts'),
            'provider_message_id' => $messageIds->count() === 1 ? $messageIds->first() : null,
            'push_error' => $errors->isEmpty() ? null : mb_substr($errors->implode(' | '), 0, 2000),
            'push_sent_at' => $children->max('sent_at'),
        ]);
    }
}
