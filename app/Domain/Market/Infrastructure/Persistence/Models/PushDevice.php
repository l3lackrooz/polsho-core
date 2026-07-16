<?php

namespace App\Domain\Market\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PushDevice extends Model
{
    protected $fillable = [
        'user_id',
        'installation_id',
        'platform',
        'provider',
        'provider_token',
        'token_hash',
        'enabled',
        'app_version',
        'locale',
        'last_seen_at',
        'invalidated_at',
    ];

    protected $hidden = [
        'provider_token',
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'provider_token' => 'encrypted',
            'enabled' => 'boolean',
            'last_seen_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pushDeliveries(): HasMany
    {
        return $this->hasMany(PriceAlertPushDelivery::class);
    }
}
