<?php

namespace App\Domain\Market\Entities;

class Asset
{

    public function __construct(
        public string $symbol,
        public string $name,
        public string $type,
        public int $precision = 8
    ) {}

}
