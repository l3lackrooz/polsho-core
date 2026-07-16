<?php

namespace Tests\Unit;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Infrastructure\Providers\Ramzinex\RamzinexMapper;
use PHPUnit\Framework\TestCase;

class RamzinexMapperTest extends TestCase
{
    public function test_uses_the_internal_provider_market_id_not_the_remote_pair_id(): void
    {
        $mapper = new RamzinexMapper;
        $subscription = new MarketSubscriptionDTO(
            instrument: 'USDT-IRR',
            remoteSymbol: 'usdtirr',
            base: 'USDT',
            quote: 'IRR',
            providerMarketId: 4,
        );

        $quotes = $mapper->mapSnapshot(
            rows: [[
                'pair_id' => 11,
                'tv_symbol' => ['ramzinex' => 'usdtirr'],
                'buy' => '1880646',
                'sell' => '1883498',
                'financial' => [
                    'last24h' => [
                        'close' => '1883500',
                        'base_volume' => '1070010.2827',
                    ],
                ],
            ]],
            subscriptions: ['usdtirr' => $subscription],
            provider: 'ramzinex',
        );

        $this->assertCount(1, $quotes);
        $this->assertSame('USDT-IRR', $quotes[0]->instrument);
        $this->assertSame(4, $quotes[0]->providerMarketId);
        $this->assertSame(1880646.0, $quotes[0]->bid);
        $this->assertSame(1883498.0, $quotes[0]->ask);
    }
}
