<?php

namespace App\Domain\Market\Contracts\Capabilities;

use App\Domain\Market\Application\DTO\OrderBookDTO;

interface SupportsOrderBook
{
    public function fetchOrderBook(string $instrument): OrderBookDTO;

}
