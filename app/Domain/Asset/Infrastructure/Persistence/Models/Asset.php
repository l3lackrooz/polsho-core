<?php

namespace App\Domain\Asset\Infrastructure\Persistence\Models;

use App\Domain\Shared\Enums\CurrencyType;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $fillable = [
        'symbol',
        'name',
        'precision',
        'status',
        'type',
        'metadata',
    ];

    protected $casts = [
        'type' => CurrencyType::class,
        'precision' => 'integer',
        'metadata' => 'array',
    ];
}
