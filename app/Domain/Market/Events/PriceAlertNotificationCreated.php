<?php

namespace App\Domain\Market\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Delivers an already-persisted inbox item to the active user's app session.
 * Push providers remain responsible for background and terminated delivery.
 */
class PriceAlertNotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    /** @param array<string, mixed> $notification */
    public function __construct(
        public readonly int $userId,
        public readonly array $notification,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'alert.notification.created';
    }

    /** @return array{notification: array<string, mixed>} */
    public function broadcastWith(): array
    {
        return ['notification' => $this->notification];
    }
}
