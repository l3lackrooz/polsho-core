<?php

namespace Tests\Unit;

use App\Domain\Market\Infrastructure\Aggregation\LatestQuoteAggregator;
use App\Domain\Market\Infrastructure\Services\MarketDataService;
use App\Domain\Market\Infrastructure\Stores\AggregateStore;
use Mockery;
use PHPUnit\Framework\TestCase;

class MarketDataServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_removes_the_cached_aggregate_when_an_instrument_has_no_active_markets(): void
    {
        $aggregator = Mockery::mock(LatestQuoteAggregator::class);
        $aggregator->shouldReceive('aggregateInstrument')
            ->once()
            ->with('USDT-IRT')
            ->andReturnNull();

        $store = Mockery::mock(AggregateStore::class);
        $store->shouldReceive('remove')
            ->once()
            ->with('USDT-IRT');

        (new MarketDataService($aggregator, $store))->aggregate('USDT-IRT');

        $this->addToAssertionCount(1);
    }
}
