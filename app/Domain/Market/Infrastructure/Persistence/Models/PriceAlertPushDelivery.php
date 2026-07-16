<?php

namespace App\Domain\Market\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceAlertPushDelivery extends Model
{
    protected $fillable = [
        'notification_delivery_id',
        'push_device_id',
        'platform',
        'provider',
        'provider_target',
        'target_hash',
        'status',
        'attempts',
        'provider_message_id',
        'error',
        'sent_at',
    ];

    protected $hidden = ['provider_target', 'target_hash'];

    protected function casts(): array
    {
        return [
            'provider_target' => 'encrypted',
            'sent_at' => 'datetime',
        ];
    }

    public function notificationDelivery(): BelongsTo
    {
        return $this->belongsTo(PriceAlertNotificationDelivery::class, 'notification_delivery_id');
    }

    public function pushDevice(): BelongsTo
    {
        return $this->belongsTo(PushDevice::class);
    }
}
