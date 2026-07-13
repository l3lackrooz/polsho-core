<?php

namespace App\Domain\Shared\Models;

use App\Domain\Shared\Enums\CurrencyType;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = [
        'code', 'name', 'type', 'precision',
        'is_active', 'is_base', 'is_convertible'
    ];

    protected $casts = [
        "type" => CurrencyType::class
    ];
}
