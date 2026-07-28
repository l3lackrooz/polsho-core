<?php

namespace App\Domain\Market\Application\Jobs;

use App\Domain\Market\Application\DTO\PushNotificationMessage;
use App\Domain\Market\Application\Services\PushNotificationTargetResolver;
use App\Domain\Market\Application\Services\PushProviderRegistry;
use App\Models\AppAnnouncement;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendAppAnnouncementPushJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $uniqueFor = 3600;

    public function __construct(private readonly int $announcementId)
    {
        Log::info("start the pusn notification step 1");

        $this->onQueue(config('queue.queues.market'));
    }

    public function uniqueId(): string
    {
        return 'announcement-push:'.$this->announcementId;
    }

    public function handle(PushNotificationTargetResolver $targets, PushProviderRegistry $providers): void
    {
        Log::info('start the push notification step 2', [
            'announcement_id' => $this->announcementId,
        ]);

        $announcement = AppAnnouncement::query()->find($this->announcementId);
        if ($announcement === null || ! $announcement->publish_push || $announcement->push_sent_at !== null) {
            Log::warning('announcement push stopped by guard', [
                'announcement_id' => $this->announcementId,
                'announcement_exists' => $announcement !== null,
                'publish_push' => $announcement?->publish_push,
                'push_sent_at' => $announcement?->push_sent_at?->toIso8601String(),
            ]);

            return;
        }

        Log::info('start the push notification step 3');
        $announcement->update(['push_status' => 'sending']);
        Log::info('start the push notification step 4');
        $message = new PushNotificationMessage(
            title: $announcement->title,
            body: $announcement->message,
            data: ['type' => 'app_announcement', 'announcement_id' => $announcement->id, 'route' => $announcement->action_url ?? ''],
            deepLink: $announcement->action_url ?? 'polsho://',
        );
        Log::info('start the push notification step 5');
        User::query()->select('id')->chunkById(100, function ($users) use ($targets, $providers, $message): void {
            foreach ($users as $user) {
                foreach ($targets->forUser($user) as $target) {
                    $providers->provider($target->provider)->send($target, $message);
                }
            }
        });
        $announcement->update(['push_status' => 'sent', 'push_sent_at' => now()]);
    }
}
