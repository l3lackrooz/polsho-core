<?php

namespace Tests\Feature;

use App\Domain\Market\Infrastructure\Providers\Tala\TalaClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TalaClientTest extends TestCase
{
    public function test_fetches_the_ajax_price_board_and_flattens_the_groups(): void
    {
        Http::fake([
            'https://www.tala.ir/ajax/price' => Http::response([
                'gold' => [
                    'gold_18k' => ['m' => 1784371725, 'v' => '18,779,000', 'v_fa' => '۱۸,۷۷۹,۰۰۰'],
                    'gold_bazartehran' => ['m' => 1784371725, 'v' => '81,347,000'],
                ],
                'sekke' => [
                    'sekke-jad' => ['m' => 1784371725, 'v' => '188,000,000'],
                ],
                // Same document also ships non-price groups: must be ignored.
                'news' => [['title' => 'irrelevant']],
                'calendar' => ['today' => '1405/04/27'],
            ]),
        ]);

        $rows = (new TalaClient(baseUrl: 'https://www.tala.ir'))->fetchPrices();

        Http::assertSent(fn ($request): bool => $request->url() === 'https://www.tala.ir/ajax/price');

        $this->assertSame(
            ['gold_18k', 'gold_bazartehran', 'sekke-jad'],
            array_keys($rows),
        );
        $this->assertSame('18,779,000', $rows['gold_18k']['v']);
    }

    public function test_the_legacy_banner_ad_endpoint_shape_yields_no_rows(): void
    {
        // /ajax/price responding with the ad-server document (regression for
        // the original misconfiguration) must produce zero rows, not garbage.
        Http::fake([
            'https://www.tala.ir/ajax/price' => Http::response(['banner' => []]),
        ]);

        $this->assertSame([], (new TalaClient(baseUrl: 'https://www.tala.ir'))->fetchPrices());
    }

    public function test_a_failed_response_throws(): void
    {
        Http::fake([
            'https://www.tala.ir/ajax/price' => Http::response('maintenance', 503),
        ]);

        $this->expectException(\RuntimeException::class);

        (new TalaClient(baseUrl: 'https://www.tala.ir'))->fetchPrices();
    }
}
