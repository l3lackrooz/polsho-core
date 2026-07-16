<?php

namespace App\Domain\Market\Application\Services;

use App\Domain\Market\Application\Jobs\SendPriceAlertPushJob;
use App\Domain\Market\Infrastructure\Notifications\PriceAlertTriggeredNotification;
use App\Domain\Market\Infrastructure\Persistence\Models\PriceAlertEvent;
use App\Domain\Market\Infrastructure\Persistence\Models\PriceAlertNotificationDelivery;
use Illuminate\Support\Facades\DB;
use Throwable;

class PriceAlertNotificationService
{
    public function __construct(
        private readonly PushNotificationTargetResolver $targets,
        private readonly PriceAlertPushDeliveryService $pushDeliveries,
    ) {}

    public function deliver(int $eventId): void
    {
        $event = PriceAlertEvent::query()
            ->with(['alert.user', 'alert.instrument'])
            ->find($eventId);

        if ($event === null || $event->type !== 'triggered' || $event->alert->user === null) {
            return;
        }

        $alert = $event->alert;
        $user = $alert->user;
        $delivery = PriceAlertNotificationDelivery::query()->firstOrCreate(
            ['price_alert_event_id' => $event->id],
            ['provider' => 'multiple', 'push_status' => 'pending'],
        );

        if ($alert->notify_in_app && $delivery->in_app_sent_at === null) {
            DB::transaction(function () use ($delivery, $user, $event): void {
                $locked = PriceAlertNotificationDelivery::query()
                    ->lockForUpdate()
                    ->findOrFail($delivery->id);

                if ($locked->in_app_sent_at === null) {
                    $user->notify(new PriceAlertTriggeredNotification($event));
                    $locked->update(['in_app_sent_at' => now()]);
                }
            });
        }

        $delivery->refresh();
        if (! $alert->notify_push) {
            $delivery->update(['push_status' => 'skipped', 'push_error' => 'Push notifications are disabled for this alert.']);

            return;
        }

        if ($delivery->push_status === 'sent') {
            return;
        }

        $targets = $this->targets->forUser($user);
        if ($targets === []) {
            $delivery->update([
                'push_status' => 'skipped',
                'push_error' => 'No active push devices are registered.',
            ]);

            return;
        }

        foreach ($targets as $target) {
            $pushDelivery = $delivery->pushDeliveries()->firstOrCreate(
                [
                    'provider' => $target->provider,
                    'target_hash' => $target->hash(),
                ],
                [
                    'push_device_id' => $target->pushDeviceId,
                    'platform' => $target->platform,
                    'provider_target' => $target->address,
                    'status' => 'pending',
                ],
            );

            if ($pushDelivery->wasRecentlyCreated) {
                SendPriceAlertPushJob::dispatch($pushDelivery->id);
            }
        }

        $this->pushDeliveries->aggregate($delivery->id);
    }

    public static function recipientId(int $userId): string
    {
        return sprintf('polsho-user-%d', $userId);
    }

    public function markDispatchFailed(int $eventId, Throwable $exception): void
    {
        PriceAlertNotificationDelivery::query()
            ->where('price_alert_event_id', $eventId)
            ->whereNotIn('push_status', ['sent', 'skipped'])
            ->update([
                'push_status' => 'failed',
                'push_error' => mb_substr($exception->getMessage(), 0, 2000),
            ]);
    }
}
