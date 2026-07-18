<?php

namespace App\Domain\Asset\Infrastructure\Persistence\Models;

use App\Domain\Shared\Enums\CurrencyType;
use App\Domain\Shared\Services\BrandingStorage;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $fillable = [
        'symbol',
        'name',
        'translations',
        'logo_path',
        'precision',
        'status',
        'type',
        'is_base_currency',
        'metadata',
    ];

    protected $casts = [
        'type' => CurrencyType::class,
        'precision' => 'integer',
        'is_base_currency' => 'boolean',
        'translations' => 'array',
        'metadata' => 'array',
    ];

    protected $appends = ['logo_url'];

    public function getLogoUrlAttribute(): ?string
    {
        return app(BrandingStorage::class)->url($this->logo_path);
    }
}
