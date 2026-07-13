<?php

namespace App\Domain\Market\Application\Jobs;

use App\Domain\Market\Infrastructure\Services\MarketDataService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AggregateInstrumentJob implements ShouldQueue
{
    public function __construct(
        private string $instrument
    ) {}

    public function handle(MarketDataService $service)
    {
        $service->aggregate($this->instrument);
    }
}

