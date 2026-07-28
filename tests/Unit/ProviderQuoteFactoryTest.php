<?php

namespace Tests\Unit;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Infrastructure\Support\Utility\ProviderQuoteFactory;
use PHPUnit\Framework\TestCase;

class ProviderQuoteFactoryTest extends TestCase
{
    public function test_it_converts_a_remote_rial_quote_to_a_toman_instrument(): void
    {
        $quote = (new ProviderQuoteFactory)->make(
            subscription: new MarketSubscriptionDTO(
                instrument: 'USDT-IRT',
                remoteSymbol: 'usdt-rls',
                base: 'USDT',
                quote: 'IRT',
                providerMarketId: 14,
            ),
            bid: 1_911_490,
            ask: 1_911_500,
            last: 1_911_500,
            provider: 'nobitex',
            volume: 125.5,
            timestamp: 1_700_000_000_000,
        );

        $this->assertSame(191_149.0, $quote->bid);
        $this->assertSame(191_150.0, $quote->ask);
        $this->assertSame(191_150.0, $quote->last);
        $this->assertSame(125.5, $quote->volume);
        $this->assertSame('USDT-IRT', $quote->instrument);
        $this->assertSame(14, $quote->providerMarketId);
    }

    public function test_it_converts_a_remote_rial_base_to_a_toman_base(): void
    {
        $quote = (new ProviderQuoteFactory)->make(
            subscription: new MarketSubscriptionDTO(
                instrument: 'IRT-USDT',
                remoteSymbol: 'rls-usdt',
                base: 'IRT',
                quote: 'USDT',
            ),
            bid: 0.0000005,
            ask: 0.0000006,
            last: null,
            provider: 'example',
            volume: 1_000,
            timestamp: 1_700_000_000_000,
        );

        $this->assertEqualsWithDelta(0.000005, $quote->bid, 0.000000000001);
        $this->assertEqualsWithDelta(0.000006, $quote->ask, 0.000000000001);
        $this->assertNull($quote->last);
        $this->assertSame(100.0, $quote->volume);
    }

    public function test_explicit_source_currency_metadata_supports_opaque_remote_symbols(): void
    {
        $quote = (new ProviderQuoteFactory)->make(
            subscription: new MarketSubscriptionDTO(
                instrument: 'USDT-IRT',
                remoteSymbol: '14',
                base: 'USDT',
                quote: 'IRT',
                metadata: ['source_quote' => 'IRR'],
            ),
            bid: 1_900_000,
            ask: 1_901_000,
            last: 1_900_500,
            provider: 'example',
            volume: null,
            timestamp: 1_700_000_000_000,
        );

        $this->assertSame(190_000.0, $quote->bid);
        $this->assertSame(190_100.0, $quote->ask);
        $this->assertSame(190_050.0, $quote->last);
    }

    public function test_matching_toman_units_are_left_unchanged(): void
    {
        $quote = (new ProviderQuoteFactory)->make(
            subscription: new MarketSubscriptionDTO(
                instrument: 'USDT-IRT',
                remoteSymbol: 'USDTTMN',
                base: 'USDT',
                quote: 'IRT',
            ),
            bid: 190_000,
            ask: 190_100,
            last: 190_050,
            provider: 'wallex',
            volume: 42,
            timestamp: 1_700_000_000_000,
        );

        $this->assertSame(190_000.0, $quote->bid);
        $this->assertSame(190_100.0, $quote->ask);
        $this->assertSame(190_050.0, $quote->last);
        $this->assertSame(42.0, $quote->volume);
    }

    public function test_it_normalizes_rial_on_both_sides_without_changing_the_price_ratio(): void
    {
        $quote = (new ProviderQuoteFactory)->make(
            subscription: new MarketSubscriptionDTO(
                instrument: 'IRT-IRT',
                remoteSymbol: 'rls-rls',
                base: 'IRT',
                quote: 'IRT',
            ),
            bid: 100,
            ask: 101,
            last: 100.5,
            provider: 'example',
            volume: 1_000,
            timestamp: 1_700_000_000_000,
        );

        $this->assertSame(100.0, $quote->bid);
        $this->assertSame(101.0, $quote->ask);
        $this->assertSame(100.5, $quote->last);
        $this->assertSame(100.0, $quote->volume);
    }
}
