<?php

namespace App\Domain\Market\Entities;

class Quote
{
    public function __construct(
        public string $instrument,
        public string $market,
        public float $bid,
        public float $ask,
        public ?float $last,
        public int $timestamp
    ) {}
}
