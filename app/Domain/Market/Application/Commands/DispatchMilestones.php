<?php

namespace App\Domain\Market\Application\Commands;

use App\Domain\Market\Application\Milestones\FanoutMilestoneJob;
use App\Domain\Market\Application\Milestones\MilestoneDelivery;
use App\Domain\Market\Application\Milestones\MilestoneEvent;
use App\Domain\Market\Application\Milestones\SendMilestoneJob;
use Illuminate\Console\Command;

class DispatchMilestones extends Command
{
    protected $signature = 'market:dispatch-milestones';

    protected $description = 'Recover pending milestone fanout and delivery work from the durable outbox';

    public function handle(): int
    {
        MilestoneEvent::whereNull('fanout_completed_at')->orderBy('id')->limit(100)->get()
            ->each(fn ($event) => FanoutMilestoneJob::dispatch($event->id));
        MilestoneDelivery::whereIn('status', ['pending', 'sending'])
            ->where(fn ($q) => $q->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('lease_until')->orWhere('lease_until', '<=', now()))
            ->orderBy('id')->limit(1000)->get()->each(fn ($row) => SendMilestoneJob::dispatch($row->id));

        return self::SUCCESS;
    }
}
