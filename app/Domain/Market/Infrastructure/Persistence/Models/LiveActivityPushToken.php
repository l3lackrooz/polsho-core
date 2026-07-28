<?php

namespace App\Domain\Market\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveActivityPushToken extends Model
{
    protected $fillable = [
        'push_device_id',
        'price_alert_id',
        'kind',
        'activity_id',
        'token',
        'token_hash',
        'enabled',
        'last_seen_at',
        'invalidated_at',
    ];

    protected $hidden = [
        'token',
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'enabled' => 'boolean',
            'last_seen_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    public function pushDevice(): BelongsTo
    {
        return $this->belongsTo(PushDevice::class);
    }

    public function priceAlert(): BelongsTo
    {
        return $this->belongsTo(PriceAlert::class);
    }
}
