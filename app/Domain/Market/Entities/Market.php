<?php

namespace App\Domain\Market\Entities;

class Market
{
    public function __construct(
        public string $id,
        public string $name,
        public string $type
    ) {}
}
