<?php

namespace Tests\Unit;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Infrastructure\Providers\Tgju\TgjuMapper;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class TgjuMapperTest extends TestCase
{
    private function subscription(string $instrument, string $remoteSymbol, int $providerMarketId): MarketSubscriptionDTO
    {
        [$base, $quote] = explode('-', strtoupper($instrument));

        return new MarketSubscriptionDTO(
            instrument: strtoupper($instrument),
            remoteSymbol: $remoteSymbol,
            base: $base,
            quote: $quote,
            providerMarketId: $providerMarketId,
        );
    }

    public function test_maps_reference_rate_to_symmetric_bid_ask(): void
    {
        $mapper = new TgjuMapper();

        $quotes = $mapper->mapSnapshot(
            rows: [
                'price_dollar_rl' => [
                    'p' => '1,781,000',
                    'h' => '1,825,200',
                    'l' => '1,779,800',
                    'ts' => '2026-07-11 18:27:12',
                ],
            ],
            subscriptions: [
                'price_dollar_rl' => $this->subscription('usd-irr', 'price_dollar_rl', 7),
            ],
            provider: 'tgju',
        );

        $this->assertCount(1, $quotes);
        $this->assertSame('USD-IRR', $quotes[0]->instrument);
        $this->assertSame(1781000.0, $quotes[0]->bid);
        $this->assertSame(1781000.0, $quotes[0]->ask);
        $this->assertSame(1781000.0, $quotes[0]->last);
        $this->assertSame('tgju', $quotes[0]->provider);
        $this->assertSame(7, $quotes[0]->providerMarketId);
        $this->assertSame(
            Carbon::parse('2026-07-11 18:27:12', 'Asia/Tehran')->getTimestampMs(),
            $quotes[0]->timestamp,
        );
    }

    public function test_skips_unsubscribed_and_unparsable_rows(): void
    {
        $mapper = new TgjuMapper();

        $quotes = $mapper->mapSnapshot(
            rows: [
                'price_eur' => ['p' => 'n/a', 'ts' => '2026-07-11 18:27:20'],
                'zinc' => ['p' => '2575.6', 'ts' => '2021-06-28 15:00:00'],
                'price_try' => ['p' => '0', 'ts' => '2026-07-11 16:51:38'],
            ],
            subscriptions: [
                'price_eur' => $this->subscription('eur-irr', 'price_eur', 8),
                'price_try' => $this->subscription('try-irr', 'price_try', 9),
            ],
            provider: 'tgju',
        );

        $this->assertSame([], $quotes);
    }

    public function test_tolerates_stray_whitespace_in_prices(): void
    {
        $mapper = new TgjuMapper();

        $quotes = $mapper->mapSnapshot(
            rows: [
                'mesghal' => ['p' => "\t757,790,000"],
            ],
            subscriptions: [
                'mesghal' => $this->subscription('xau-irr', 'mesghal', 10),
            ],
            provider: 'tgju',
        );

        $this->assertCount(1, $quotes);
        $this->assertSame(757790000.0, $quotes[0]->bid);
    }
}
