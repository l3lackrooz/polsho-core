<?php

namespace Tests\Unit;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Infrastructure\Providers\Nobitex\NobitexMapper;
use PHPUnit\Framework\TestCase;

class NobitexMapperTest extends TestCase
{
    public function test_it_maps_the_rial_feed_into_the_canonical_toman_market(): void
    {
        $subscription = new MarketSubscriptionDTO(
            instrument: 'USDT-IRT',
            remoteSymbol: 'usdt-rls',
            base: 'USDT',
            quote: 'IRT',
            providerMarketId: 14,
        );

        $quotes = (new NobitexMapper)->mapSnapshot(
            rows: [
                'usdt-rls' => [
                    'bestBuy' => '1911490',
                    'bestSell' => '1911500',
                    'latest' => '1911500',
                    'volumeSrc' => '12.5',
                ],
            ],
            subscriptions: ['usdt-rls' => $subscription],
            provider: 'nobitex',
        );

        $this->assertCount(1, $quotes);
        $this->assertSame(191_149.0, $quotes[0]->bid);
        $this->assertSame(191_150.0, $quotes[0]->ask);
        $this->assertSame(191_150.0, $quotes[0]->last);
    }
}
