<?php

namespace App\Domain\Market\Application\Jobs;

use App\Domain\Market\Application\Services\PriceAlertLiveActivityService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EndPriceAlertLiveActivityJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 7200;
    public function __construct(private readonly int $alertId) { $this->onQueue(config('queue.queues.market')); }
    public function uniqueId(): string { return 'live-activity-end:'.$this->alertId; }
    public function handle(PriceAlertLiveActivityService $activities): void { $activities->end($this->alertId); }
}
