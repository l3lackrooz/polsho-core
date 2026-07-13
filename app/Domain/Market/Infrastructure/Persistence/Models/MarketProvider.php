<?php

namespace App\Domain\Market\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketProvider extends Model
{
    protected $table = 'market_providers';

    protected $fillable = [
        'name',
        'driver',
        'slug',
        'base_url',
        'description',
        'status',
        'is_default',
        'priority',
        'config',
    ];

    protected $casts = [
        'config' => 'array',
        'is_default' => 'boolean',
    ];

    public function markets(): HasMany
    {
        return $this->hasMany(ProviderMarket::class, 'provider_id');
    }
}
