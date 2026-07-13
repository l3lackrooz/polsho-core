<?php

namespace App\Domain\Asset\Models;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Instrument extends Model
{
    protected $fillable = ['base_asset_id', 'quote_asset_id', 'symbol', 'status', 'metadata'];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function baseAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'base_asset_id');
    }

    public function quoteAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'quote_asset_id');
    }
}
