<?php

namespace App\Domain\Market\Contracts;

interface MarketDataProviderInterface
{
    public function name(): string;

    public function healthCheck(): bool;
}
