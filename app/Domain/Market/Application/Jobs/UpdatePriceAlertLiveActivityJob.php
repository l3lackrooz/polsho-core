<?php

namespace App\Domain\Market\Application\Jobs;

use App\Domain\Market\Application\Services\PriceAlertLiveActivityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class UpdatePriceAlertLiveActivityJob implements ShouldQueue
{
    use Queueable;
    public function __construct(private readonly int $alertId, private readonly float $price, private readonly string $provider, private readonly int $timestamp) { $this->onQueue(config('queue.queues.market')); }
    public function handle(PriceAlertLiveActivityService $activities): void { $activities->update($this->alertId, $this->price, $this->provider, $this->timestamp); }
}
