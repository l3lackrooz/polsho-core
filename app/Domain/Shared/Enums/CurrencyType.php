<?php

namespace App\Domain\Shared\Enums;

enum CurrencyType: string
{
    case FIAT = 'fiat';
    case CRYPTO = 'crypto';
    case METAL = 'metal';
    case INTERNAL = 'internal';
}
