<?php

namespace App\Domain\Market\Infrastructure\Persistence\Models;

use App\Domain\Shared\Services\BrandingStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MarketProvider extends Model
{
    protected $table = 'market_providers';

    protected $fillable = [
        'name',
        'translations',
        'driver',
        'slug',
        'base_url',
        'homepage_url',
        'description',
        'logo_path',
        'status',
        'is_default',
        'priority',
        'config',
    ];

    protected $casts = [
        'config' => 'array',
        'translations' => 'array',
        'is_default' => 'boolean',
    ];

    protected $appends = ['logo_url'];

    public function getLogoUrlAttribute(): ?string
    {
        return app(BrandingStorage::class)->url($this->logo_path);
    }

    public function markets(): HasMany
    {
        return $this->hasMany(ProviderMarket::class, 'provider_id');
    }

    /**
     * Public, editorial content for this provider. Runtime connection settings
     * remain on the provider itself and are never exposed through this relation.
     */
    public function profile(): HasOne
    {
        return $this->hasOne(MarketProviderProfile::class, 'provider_id');
    }
}
