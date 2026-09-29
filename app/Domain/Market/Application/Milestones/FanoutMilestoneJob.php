<?php

namespace App\Domain\Market\Application\Milestones;

use App\Domain\Market\Application\Services\PushNotificationTargetResolver;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class FanoutMilestoneJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 120;

    public function uniqueId(): string
    {
        return (string) $this->eventId;
    }

    public int $tries = 3;

    public int $timeout = 45;

    public function __construct(public int $eventId)
    {
        $this->onQueue(config('queue.queues.notifications'));
    }

    public function handle(PushNotificationTargetResolver $resolver): void
    {
        $more = DB::transaction(function () use ($resolver) {
            $event = MilestoneEvent::lockForUpdate()->find($this->eventId);
            if (! $event || $event->fanout_completed_at) {
                return false;
            }
            if ($event->expires_at->isPast() || ! $event->rule?->enabled || $event->revision != $event->rule->revision) {
                $event->update(['fanout_completed_at' => now()]);

                return false;
            }
            $users = User::where('market_milestones_enabled', true)->where('id', '>', $event->fanout_cursor)->where('id', '<=', $event->audience_max_user_id)
                ->whereHas('pushDevices', fn ($q) => $q->where('enabled', true)->where('created_at', '<=', $event->created_at))
                ->orderBy('id')->limit(100)->get();
            foreach ($users as $user) {
                foreach ($resolver->forUser($user) as $target) {
                    $device = $target->pushDeviceId ? $user->pushDevices->firstWhere('id', $target->pushDeviceId)
                        : $user->pushDevices->where('enabled', true)->where('provider', $target->provider)->sortByDesc('last_seen_at')->first();
                    if (! $device || $device->created_at->gt($event->created_at)) {
                        continue;
                    }
                    $delivery = $event->deliveries()->firstOrCreate([
                        'provider' => $target->provider, 'target_hash' => hash('sha256', $target->address),
                    ], [
                        'user_id' => $user->id, 'push_device_id' => $target->pushDeviceId,
                        'platform' => $target->platform, 'address' => $target->address,
                        'locale' => $target->locale, 'available_at' => now(),
                    ]);
                    SendMilestoneJob::dispatch($delivery->id)->afterCommit();
                }
            }
            $event->update(['fanout_cursor' => $users->last()?->id ?? $event->fanout_cursor,
                'fanout_completed_at' => $users->count() < 100 ? now() : null]);

            return $users->count() === 100;
        });
        if ($more) {
            self::dispatch($this->eventId);
        }
    }
}
