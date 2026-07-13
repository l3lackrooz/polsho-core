<?php

namespace App\Domain\Market\Entities;

class Instrument
{
    public function __construct(
        public string $symbol,
        public string $baseAsset,
        public string $quoteAsset
    ) {}
}
