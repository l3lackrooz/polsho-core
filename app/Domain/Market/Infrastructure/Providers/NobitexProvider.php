<?php

namespace App\Domain\Market\Infrastructure\Providers;

use App\Domain\Market\Application\DTO\QuoteDTO;
use Illuminate\Support\Facades\Http;

class NobitexProvider extends BaseFeeder
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function fetch(): array
    {
        $response = Http::get('https://apiv2.nobitex.ir/v3/orderbook/BTCIRT');
        $data = $response->json()['stats'];
        $quotes = [];

        foreach ($data as $symbol => $stat) {

           
        }

        return $quotes;

    }
}
