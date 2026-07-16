<?php

namespace App\Domain\Market\Application\Jobs;

use App\Domain\Market\Application\Services\PriceAlertPushDeliveryService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendPriceAlertPushJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 300;

    /** @var array<int, int> */
    public array $backoff = [10, 60];

    public function __construct(private readonly int $deliveryId)
    {
        $this->onQueue(config('queue.queues.market'));
    }

    public function uniqueId(): string
    {
        return (string) $this->deliveryId;
    }

    public function handle(PriceAlertPushDeliveryService $deliveries): void
    {
        $deliveries->send($this->deliveryId);
    }

    public function failed(Throwable $exception): void
    {
        app(PriceAlertPushDeliveryService::class)->markFailed($this->deliveryId, $exception);
    }
}
