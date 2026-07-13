<?php

namespace App\Domain\Market\Contracts\Capabilities;

use Illuminate\Support\Collection;

interface SupportsPriceSnapshot
{
    /**
     * @param Collection<int, mixed> $instruments
     * @return array<int, \App\Domain\Market\Application\DTO\QuoteDTO>
     */
    public function fetchPrices(Collection $instruments): array;
}
