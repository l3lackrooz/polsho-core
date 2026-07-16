<?php

namespace App\Domain\Market\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceAlertNotificationDelivery extends Model
{
    protected $fillable = [
        'price_alert_event_id',
        'provider',
        'in_app_sent_at',
        'push_status',
        'push_attempts',
        'provider_message_id',
        'push_error',
        'push_sent_at',
    ];

    protected $casts = [
        'in_app_sent_at' => 'datetime',
        'push_sent_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(PriceAlertEvent::class, 'price_alert_event_id');
    }

    public function pushDeliveries(): HasMany
    {
        return $this->hasMany(PriceAlertPushDelivery::class, 'notification_delivery_id');
    }
}
