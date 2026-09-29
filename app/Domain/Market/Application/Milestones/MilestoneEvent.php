<?php

namespace App\Domain\Market\Application\Milestones;

use Illuminate\Database\Eloquent\Model;

class MilestoneEvent extends Model
{
    protected $table = 'market_milestone_events';

    protected $guarded = ['id'];

    protected $casts = ['matches' => 'array', 'level' => 'decimal:8', 'price' => 'decimal:8', 'expires_at' => 'datetime', 'fanout_completed_at' => 'datetime'];

    public function rule()
    {
        return $this->belongsTo(MilestoneRule::class, 'rule_id');
    }

    public function deliveries()
    {
        return $this->hasMany(MilestoneDelivery::class, 'event_id');
    }
}
