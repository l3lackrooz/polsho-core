<?php

namespace App\Domain\Market\Application\DTO;

class OrderBookDTO
{
    public function __construct(
        public string $provider,
        public string $instrument,
        public array $bids,
        public array $asks,
        public int $timestamp
    ) {}
}
