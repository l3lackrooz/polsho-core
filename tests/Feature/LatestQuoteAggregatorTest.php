<?php

namespace Tests\Feature;

use App\Domain\Market\Infrastructure\Aggregation\LatestQuoteAggregator;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Infrastructure\Stores\LatestQuoteStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LatestQuoteAggregatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_includes_reference_quotes_without_using_them_for_best_prices(): void
    {
        $this->createProvider('tgju', [
            'is_reference' => true,
            'max_quote_age_seconds' => 1800,
        ]);
        $this->createProvider('nobitex');
        $this->createProvider('ramzinex');
        $timestamp = now()->getTimestampMs();

        $aggregated = $this->aggregate([
            'tgju' => $this->quote(
                'tgju',
                1_775_600,
                1_775_600,
                now()->subMinutes(15)->getTimestampMs(),
                10,
            ),
            'nobitex' => $this->quote('nobitex', 1_774_000, 1_774_300, $timestamp, 3),
            'ramzinex' => $this->quote('ramzinex', 1_773_900, 1_774_100, $timestamp, 11),
        ]);

        $this->assertNotNull($aggregated);
        $this->assertSame('nobitex', $aggregated->bestBid?->provider);
        $this->assertSame('ramzinex', $aggregated->bestAsk?->provider);

        $providers = collect($aggregated->toArray()['providers'])->keyBy('provider');

        $this->assertTrue($providers['tgju']['is_reference']);
        $this->assertFalse($providers['tgju']['is_best_bid']);
        $this->assertFalse($providers['tgju']['is_best_ask']);
        $this->assertTrue($providers['nobitex']['is_best_bid']);
        $this->assertTrue($providers['ramzinex']['is_best_ask']);
    }

    public function test_excludes_a_quote_older_than_five_seconds_using_millisecond_timestamps(): void
    {
        $this->createProvider('nobitex');

        $aggregated = $this->aggregate([
            'nobitex' => $this->quote(
                'nobitex',
                1_774_000,
                1_774_300,
                now()->subSeconds(6)->getTimestampMs(),
                3,
            ),
        ]);

        $this->assertNull($aggregated);
    }

    public function test_uses_a_reference_rate_as_the_best_quote_when_it_is_the_only_provider(): void
    {
        $this->createProvider('tgju', [
            'is_reference' => true,
            'max_quote_age_seconds' => 1800,
        ]);

        $aggregated = $this->aggregate([
            'tgju' => $this->quote(
                'tgju',
                1_782_000,
                1_782_000,
                now()->subMinutes(15)->getTimestampMs(),
                8,
            ),
        ]);

        $this->assertNotNull($aggregated);
        $this->assertSame('tgju', $aggregated->bestBid?->provider);
        $this->assertSame('tgju', $aggregated->bestAsk?->provider);
        $this->assertTrue($aggregated->providers[0]->isReference);
        $this->assertTrue($aggregated->toArray()['providers'][0]['is_best_bid']);
        $this->assertTrue($aggregated->toArray()['providers'][0]['is_best_ask']);
    }

    /** @param array<string, array<string, int|string|float|null>> $rows */
    private function aggregate(array $rows): ?\App\Domain\Market\Application\DTO\AggregatedQuoteDTO
    {
        return (new LatestQuoteAggregator(new class($rows) extends LatestQuoteStore {
            /** @param array<string, array<string, int|string|float|null>> $rows */
            public function __construct(private readonly array $rows) {}

            public function getAll(string $instrument): array
            {
                return $this->rows;
            }
        }))->aggregateInstrument('USDT-IRR');
    }

    /** @param array<string, mixed> $config */
    private function createProvider(string $slug, array $config = []): void
    {
        MarketProvider::query()->create([
            'name' => strtoupper($slug),
            'slug' => $slug,
            'driver' => sprintf('Tests\\%sDriver', ucfirst($slug)),
            'base_url' => 'https://example.test',
            'config' => $config,
        ]);
    }

    /** @return array<string, int|string|float|null> */
    private function quote(
        string $provider,
        float $bid,
        float $ask,
        int $timestamp,
        int $providerMarketId,
    ): array {
        return [
            'instrument' => 'USDT-IRR',
            'provider' => $provider,
            'bid' => $bid,
            'ask' => $ask,
            'last' => $ask,
            'volume' => null,
            'timestamp' => $timestamp,
            'provider_market_id' => $providerMarketId,
        ];
    }
}
