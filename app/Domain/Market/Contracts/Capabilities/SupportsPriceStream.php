<?php

namespace App\Domain\Market\Contracts\Capabilities;

use Illuminate\Support\Collection;

interface SupportsPriceStream
{
    /**
     * @param Collection<int, mixed> $instruments
     * @param callable $handler Receives a \App\Domain\Market\Application\DTO\QuoteDTO instance.
     */
    public function subscribePrices(Collection $instruments, callable $handler): void;
}
