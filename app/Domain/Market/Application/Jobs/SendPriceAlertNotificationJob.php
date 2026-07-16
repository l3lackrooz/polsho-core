<?php

namespace App\Domain\Market\Application\Jobs;

use App\Domain\Market\Application\Services\PriceAlertNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendPriceAlertNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60];

    public function __construct(private readonly int $eventId)
    {
        $this->onQueue(config('queue.queues.market'));
    }

    public function handle(PriceAlertNotificationService $notifications): void
    {
        $notifications->deliver($this->eventId);
    }

    public function failed(Throwable $exception): void
    {
        app(PriceAlertNotificationService::class)->markDispatchFailed($this->eventId, $exception);
    }
}
