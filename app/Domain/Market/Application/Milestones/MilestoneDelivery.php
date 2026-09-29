<?php

namespace App\Domain\Market\Application\Milestones;

use Illuminate\Database\Eloquent\Model;

class MilestoneDelivery extends Model
{
    protected $table = 'market_milestone_deliveries';

    protected $guarded = ['id'];

    protected $hidden = ['address', 'target_hash'];

    protected $casts = ['address' => 'encrypted', 'available_at' => 'datetime', 'lease_until' => 'datetime'];

    public function event()
    {
        return $this->belongsTo(MilestoneEvent::class, 'event_id');
    }
}
