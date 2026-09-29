<?php

namespace App\Domain\Market\Application\Milestones;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluateMilestonesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public array $aggregate)
    {
        $this->onQueue(config('queue.queues.market'));
    }

    public function handle(MilestoneEvaluator $evaluator): void
    {
        $evaluator->evaluate($this->aggregate);
    }
}
