<?php

namespace App\Domain\Market\Listeners;

use App\Domain\Market\Events\MarketDataUpdated;
use App\Domain\Market\Infrastructure\Notifications\BalePriceAlertService;
use Illuminate\Support\Facades\Log;

class NotifyMarketDataListener
{
    public function __construct(
        private BalePriceAlertService $notifier
    ) {}

    public function handle(MarketDataUpdated $event): void
    {
     //   Log::info("Notifi Bale", [$event]);
        $this->notifier->sendFor($event->aggregated);
    }
}
