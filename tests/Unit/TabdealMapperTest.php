<?php

namespace Tests\Unit;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Infrastructure\Providers\Tabdeal\TabdealMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TabdealMapperTest extends TestCase
{
    #[DataProvider('invalidPrices')]
    public function test_skips_invalid_prices_without_losing_other_markets(mixed $price): void
    {
        $quotes = (new TabdealMapper)->mapSnapshot([
            'BTC' => ['IRT' => ['price' => $price], 'USDT' => null],
            'ETH' => null,
            'USDT' => ['IRT' => ['price' => '262749']],
        ], [
            'BTCIRT' => new MarketSubscriptionDTO('BTC-IRT', 'BTCIRT', 'BTC', 'IRT'),
            'BTCUSDT' => new MarketSubscriptionDTO('BTC-USDT', 'BTCUSDT', 'BTC', 'USDT'),
            'USDTIRT' => new MarketSubscriptionDTO('USDT-IRT', 'USDTIRT', 'USDT', 'IRT'),
        ], 'tabdeal');

        $this->assertCount(1, $quotes);
        $this->assertSame('USDT-IRT', $quotes[0]->instrument);
    }

    public static function invalidPrices(): array
    {
        return [[null], [''], ['unavailable'], [0], [-1], ['1e999'], [true], [[]]];
    }

    public function test_converts_toman_to_rial_for_a_rial_instrument(): void
    {
        $quotes = (new TabdealMapper)->mapSnapshot(
            ['USDT' => ['IRT' => ['price' => '262749']]],
            ['USDTIRT' => new MarketSubscriptionDTO('USDT-IRR', 'USDTIRT', 'USDT', 'IRR', 42)],
            'tabdeal',
        );

        $this->assertCount(1, $quotes);
        $this->assertSame(2627490.0, $quotes[0]->last);
        $this->assertSame(2627490.0, $quotes[0]->bid);
        $this->assertSame(2627490.0, $quotes[0]->ask);
        $this->assertSame(42, $quotes[0]->providerMarketId);
    }
}
