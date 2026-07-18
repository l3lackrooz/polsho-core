<?php

namespace Tests\Unit;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Infrastructure\Providers\Tala\TalaMapper;
use PHPUnit\Framework\TestCase;

class TalaMapperTest extends TestCase
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

    public function test_geram18_alias_resolves_to_the_gold_18k_feed_row(): void
    {
        $quotes = (new TalaMapper())->mapSnapshot(
            rows: [
                'gold_18k' => ['m' => 1784371725, 'v' => '18,779,000', 'c' => 'طلاي 18 عيار'],
                'gold_bazartehran' => ['m' => 1784371725, 'v' => '81,347,000', 'c' => 'مظنه بازار کيلو'],
            ],
            subscriptions: [
                'geram18' => $this->subscription('geram18-irr', 'geram18', 21),
                'bazartehran' => $this->subscription('mesghal-irr', 'bazartehran', 22),
            ],
            provider: 'tala',
        );

        $this->assertCount(2, $quotes);
        $this->assertSame('GERAM18-IRR', $quotes[0]->instrument);
        $this->assertSame(18779000.0, $quotes[0]->bid);
        $this->assertSame(18779000.0, $quotes[0]->ask);
        $this->assertSame(18779000.0, $quotes[0]->last);
        $this->assertSame('tala', $quotes[0]->provider);
        $this->assertSame(21, $quotes[0]->providerMarketId);
        // Board update time (`m`, epoch seconds) carries through as ms.
        $this->assertSame(1784371725000, $quotes[0]->timestamp);

        $this->assertSame('MESGHAL-IRR', $quotes[1]->instrument);
        $this->assertSame(81347000.0, $quotes[1]->bid);
    }

    public function test_exact_feed_ids_and_gold_prefix_fallback_resolve(): void
    {
        $quotes = (new TalaMapper())->mapSnapshot(
            rows: [
                'gold_ounce' => ['m' => 1784371725, 'v' => '3,412.50'],
                'sekke-jad' => ['m' => 1784371725, 'v' => '188,000,000'],
            ],
            subscriptions: [
                // `ounce` has no alias entry: resolved via the gold_ prefix.
                'ounce' => $this->subscription('xau-usd', 'ounce', 31),
                // Exact feed id passes straight through.
                'sekke-jad' => $this->subscription('sekke-irr', 'sekke-jad', 32),
            ],
            provider: 'tala',
        );

        $this->assertCount(2, $quotes);
        $this->assertSame(3412.5, $quotes[0]->bid);
        $this->assertSame(188000000.0, $quotes[1]->bid);
    }

    public function test_persian_numerals_parse_and_zero_or_missing_rows_are_skipped(): void
    {
        $quotes = (new TalaMapper())->mapSnapshot(
            rows: [
                'gold_18k' => ['m' => 1784371725, 'v' => '۱۸٬۷۷۹٬۰۰۰'],
                'gold_24k' => ['m' => 1784371725, 'v' => '0'],
            ],
            subscriptions: [
                'geram18' => $this->subscription('geram18-irr', 'geram18', 21),
                '24k' => $this->subscription('geram24-irr', '24k', 23),
                'missing' => $this->subscription('foo-irr', 'missing', 24),
            ],
            provider: 'tala',
        );

        // The zero-value 24k row and the absent row are both dropped.
        $this->assertCount(1, $quotes);
        $this->assertSame(18779000.0, $quotes[0]->bid);
    }
}
