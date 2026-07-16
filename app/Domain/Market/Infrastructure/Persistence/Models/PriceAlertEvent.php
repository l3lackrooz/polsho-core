<?php

namespace App\Domain\Market\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PriceAlertEvent extends Model
{
    protected $fillable = ['price_alert_id', 'type', 'payload', 'occurred_at'];

    protected $casts = ['payload' => 'array', 'occurred_at' => 'datetime'];

    public function alert(): BelongsTo
    {
        return $this->belongsTo(PriceAlert::class, 'price_alert_id');
    }

    public function notificationDelivery(): HasOne
    {
        return $this->hasOne(PriceAlertNotificationDelivery::class);
    }
}
