<?php

namespace Tests\Unit;

use App\Domain\Market\Application\DTO\AggregatedQuoteDTO;
use App\Domain\Market\Application\DTO\QuoteDTO;
use App\Domain\Market\Infrastructure\Notifications\BalePriceAlertFormatter;
use Carbon\Carbon;
use Tests\TestCase;

class BalePriceAlertFormatterTest extends TestCase
{
    public function test_it_formats_a_bale_price_message(): void
    {
        Carbon::setTestNow('2025-04-23 14:32:10');

        $message = app(BalePriceAlertFormatter::class)->format(
            new AggregatedQuoteDTO(
                instrument: 'BTC-USDT',
                bestBid: new QuoteDTO('BTC-USDT', 63_410, 63_430, 63_420, 'nobitex', 1_200_000_000, Carbon::now()->getTimestampMs()),
                bestAsk: new QuoteDTO('BTC-USDT', 63_405, 63_415, 63_410, 'ramzinex', 950_000_000, Carbon::now()->getTimestampMs()),
                providers: [
                    new QuoteDTO('BTC-USDT', 63_410, 63_430, 63_420, 'nobitex', 1_200_000_000, Carbon::now()->getTimestampMs()),
                    new QuoteDTO('BTC-USDT', 63_405, 63_415, 63_410, 'ramzinex', 950_000_000, Carbon::now()->getTimestampMs()),
                ],
                timestamp: Carbon::now()->getTimestampMs(),
            ),
            ['nobitex' => 2.4, 'ramzinex' => 1.8],
        );

        $this->assertStringContainsString('🚀 BTC/USDT', $message);
        $this->assertStringContainsString('🕒 18:02:10', $message);
        $this->assertStringContainsString('📌 مرجع: 63,415.00 USDT', $message);
        $this->assertStringContainsString('Nobitex: 63,420.00 USDT (+0.01%)', $message);
        $this->assertStringContainsString('Ramzinex: 63,410.00 USDT (-0.01%)', $message);
        $this->assertStringContainsString('🟢 بهترین خرید: Ramzinex - 63,415.00 USDT', $message);
        $this->assertStringContainsString('🔴 بهترین فروش: Nobitex - 63,410.00 USDT', $message);
        $this->assertStringContainsString('📦 حجم: 1.2B', $message);
        $this->assertStringContainsString('📊 تغییر ۲۴ساعت: Nobitex +2.40% | Ramzinex +1.80%', $message);
    }

    public function test_it_omits_decimals_for_irr_pairs(): void
    {
        Carbon::setTestNow('2025-04-23 14:32:10');

        $message = app(BalePriceAlertFormatter::class)->format(
            new AggregatedQuoteDTO(
                instrument: 'BTC-IRT',
                bestBid: new QuoteDTO('BTC-IRT', 4_240_000, 4_255_000, 4_250_000, 'nobitex', 125_000_000, Carbon::now()->getTimestampMs()),
                bestAsk: new QuoteDTO('BTC-IRT', 4_245_000, 4_250_000, 4_248_000, 'ramzinex', 120_000_000, Carbon::now()->getTimestampMs()),
                providers: [
                    new QuoteDTO('BTC-IRT', 4_240_000, 4_255_000, 4_250_000, 'nobitex', 125_000_000, Carbon::now()->getTimestampMs()),
                    new QuoteDTO('BTC-IRT', 4_245_000, 4_250_000, 4_248_000, 'ramzinex', 120_000_000, Carbon::now()->getTimestampMs()),
                ],
                timestamp: Carbon::now()->getTimestampMs(),
            ),
            ['nobitex' => 1.8],
        );

        $this->assertStringContainsString('Nobitex: 4,250,000 IRT', $message);
        $this->assertStringContainsString('🟢 بهترین خرید: Ramzinex - 4,250,000 IRT', $message);
    }
}
