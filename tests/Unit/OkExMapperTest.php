<?php

namespace Tests\Unit;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Infrastructure\Providers\OkEx\OkExMapper;
use PHPUnit\Framework\TestCase;

class OkExMapperTest extends TestCase
{
    public function test_it_combines_ticker_and_order_book_data_for_a_subscribed_market(): void
    {
        $subscription = new MarketSubscriptionDTO(
            instrument: 'BTC-USDT',
            remoteSymbol: 'BTC-USDT',
            base: 'BTC',
            quote: 'USDT',
            providerMarketId: 22,
        );

        $quotes = (new OkExMapper)->mapSnapshot(
            tickers: [[
                'symbol' => 'BTC-USDT',
                'lastPrice' => '63098',
                'volume' => '712.26053',
                'updateTime' => '1785586916535',
            ]],
            orderBooks: [
                'BTC-USDT' => [
                    'bids' => [[63093.56, 0.01855]],
                    'asks' => [[63098.63, 0.01458]],
                ],
            ],
            subscriptions: ['BTC-USDT' => $subscription],
            provider: 'ok-ex',
        );

        $this->assertCount(1, $quotes);
        $this->assertSame('BTC-USDT', $quotes[0]->instrument);
        $this->assertSame(22, $quotes[0]->providerMarketId);
        $this->assertSame(63093.56, $quotes[0]->bid);
        $this->assertSame(63098.63, $quotes[0]->ask);
        $this->assertSame(63098.0, $quotes[0]->last);
        $this->assertSame(712.26053, $quotes[0]->volume);
        $this->assertSame(1785586916535, $quotes[0]->timestamp);
    }
}
