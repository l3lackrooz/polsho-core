<?php

namespace App\Domain\Market\Application\Milestones;

use App\Domain\Asset\Models\Instrument;
use App\Domain\Market\Infrastructure\Persistence\Models\ProviderMarket;
use Illuminate\Database\Eloquent\Model;

class MilestoneRule extends Model
{
    protected $table = 'market_milestone_rules';

    protected $guarded = ['id'];

    protected $casts = ['enabled' => 'boolean', 'step' => 'decimal:8', 'state' => 'array'];

    public function instrument()
    {
        return $this->belongsTo(Instrument::class);
    }

    public function market()
    {
        return $this->belongsTo(ProviderMarket::class, 'provider_market_id');
    }

    public function events()
    {
        return $this->hasMany(MilestoneEvent::class, 'rule_id');
    }
}
