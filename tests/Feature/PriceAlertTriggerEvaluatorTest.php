<?php

namespace Tests\Feature;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Asset\Models\Instrument;
use App\Domain\Market\Application\DTO\AggregatedQuoteDTO;
use App\Domain\Market\Application\DTO\QuoteDTO;
use App\Domain\Market\Application\Jobs\EvaluatePriceAlertsJob;
use App\Domain\Market\Application\Services\PriceAlertTriggerEvaluator;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Infrastructure\Persistence\Models\PriceAlert;
use App\Domain\Market\Infrastructure\Persistence\Models\ProviderMarket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceAlertTriggerEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_triggers_a_one_time_best_market_alert_from_a_fresh_tradable_quote(): void
    {
        [$instrument, $market] = $this->market();
        $alert = $this->alert($instrument, [
            'scope' => 'best_market',
            'condition' => 'goes_above',
            'target_price' => 100,
            'repeat' => 'once',
        ]);

        $triggered = app(PriceAlertTriggerEvaluator::class)->evaluate(
            $this->aggregate($this->quote($instrument->symbol, $market->id, 101)),
        );

        $alert->refresh();

        $this->assertSame(1, $triggered);
        $this->assertSame('triggered', $alert->status);
        $this->assertNotNull($alert->last_triggered_at);
        $this->assertSame(1, $alert->events()->where('type', 'triggered')->count());
        $this->assertSame(101.0, (float) $alert->events()->latest('id')->first()->payload['price']);
    }

    public function test_recurring_alert_rearms_only_after_the_price_crosses_back(): void
    {
        [$instrument, $market] = $this->market();
        $alert = $this->alert($instrument, [
            'provider_market_id' => $market->id,
            'scope' => 'specific_exchange',
            'condition' => 'goes_above',
            'target_price' => 100,
            'repeat' => 'recurring',
        ]);
        $evaluator = app(PriceAlertTriggerEvaluator::class);

        $evaluator->evaluate($this->aggregate($this->quote($instrument->symbol, $market->id, 99)));
        $evaluator->evaluate($this->aggregate($this->quote($instrument->symbol, $market->id, 101)));
        $evaluator->evaluate($this->aggregate($this->quote($instrument->symbol, $market->id, 102)));
        $evaluator->evaluate($this->aggregate($this->quote($instrument->symbol, $market->id, 99)));
        $evaluator->evaluate($this->aggregate($this->quote($instrument->symbol, $market->id, 101)));

        $alert->refresh();

        $this->assertSame('active', $alert->status);
        $this->assertSame(2, $alert->events()->where('type', 'triggered')->count());
        $this->assertSame(101.0, (float) $alert->metadata['evaluation']['last_price']);
    }

    public function test_ignores_stale_quotes_even_when_the_aggregate_payload_contains_one(): void
    {
        [$instrument, $market] = $this->market();
        $alert = $this->alert($instrument, [
            'scope' => 'best_market',
            'condition' => 'goes_above',
            'target_price' => 100,
        ]);

        $triggered = app(PriceAlertTriggerEvaluator::class)->evaluate(
            $this->aggregate(
                $this->quote(
                    $instrument->symbol,
                    $market->id,
                    101,
                    timestamp: now()->subSeconds(61)->getTimestampMs(),
                ),
            ),
        );

        $alert->refresh();

        $this->assertSame(0, $triggered);
        $this->assertSame('active', $alert->status);
        $this->assertSame(0, $alert->events()->where('type', 'triggered')->count());
    }

    public function test_reference_quotes_only_trigger_alerts_explicitly_scoped_to_that_provider(): void
    {
        [$instrument, $referenceMarket] = $this->market(reference: true);
        $bestMarketAlert = $this->alert($instrument, [
            'scope' => 'best_market',
            'condition' => 'goes_above',
            'target_price' => 100,
        ]);
        $referenceAlert = $this->alert($instrument, [
            'provider_market_id' => $referenceMarket->id,
            'scope' => 'specific_exchange',
            'condition' => 'goes_above',
            'target_price' => 100,
        ]);

        $triggered = app(PriceAlertTriggerEvaluator::class)->evaluate(
            $this->aggregate(
                $this->quote(
                    $instrument->symbol,
                    $referenceMarket->id,
                    101,
                    reference: true,
                    timestamp: now()->subMinutes(15)->getTimestampMs(),
                ),
            ),
        );

        $bestMarketAlert->refresh();
        $referenceAlert->refresh();

        $this->assertSame(1, $triggered);
        $this->assertSame('active', $bestMarketAlert->status);
        $this->assertSame('triggered', $referenceAlert->status);
        $this->assertTrue((bool) $referenceAlert->events()->latest('id')->first()->payload['is_reference']);
    }

    public function test_queued_evaluation_rehydrates_the_aggregate_payload(): void
    {
        [$instrument, $market] = $this->market();
        $alert = $this->alert($instrument, [
            'scope' => 'best_market',
            'condition' => 'goes_above',
            'target_price' => 100,
        ]);
        $aggregate = $this->aggregate($this->quote($instrument->symbol, $market->id, 101));

        (new EvaluatePriceAlertsJob($aggregate->toArray()))
            ->handle(app(PriceAlertTriggerEvaluator::class));

        $this->assertSame('triggered', $alert->refresh()->status);
    }

    /** @return array{Instrument, ProviderMarket} */
    private function market(bool $reference = false): array
    {
        $base = Asset::query()->create(['symbol' => 'USDT', 'name' => 'Tether', 'type' => 'crypto']);
        $quote = Asset::query()->create(['symbol' => 'IRT', 'name' => 'Iranian Toman', 'type' => 'fiat']);
        $instrument = Instrument::query()->create([
            'base_asset_id' => $base->id,
            'quote_asset_id' => $quote->id,
            'symbol' => 'USDT-IRT',
        ]);
        $provider = MarketProvider::query()->create([
            'name' => $reference ? 'TGJU' : 'Nobitex',
            'driver' => $reference ? 'tgju' : 'nobitex',
            'slug' => $reference ? 'tgju' : 'nobitex',
            'base_url' => 'https://example.test',
            'config' => ['is_reference' => $reference],
        ]);
        $market = ProviderMarket::query()->create([
            'provider_id' => $provider->id,
            'instrument_id' => $instrument->id,
            'remote_symbol' => $instrument->symbol,
        ]);

        return [$instrument, $market];
    }

    /** @param array<string, mixed> $attributes */
    private function alert(Instrument $instrument, array $attributes): PriceAlert
    {
        return PriceAlert::query()->create([
            'user_id' => User::factory()->create()->id,
            'instrument_id' => $instrument->id,
            'provider_market_id' => null,
            'scope' => 'best_market',
            'condition' => 'reaches',
            'target_price' => 1,
            'status' => 'active',
            'repeat' => 'once',
            'notify_push' => true,
            'notify_in_app' => true,
            ...$attributes,
        ]);
    }

    private function aggregate(QuoteDTO $quote): AggregatedQuoteDTO
    {
        return new AggregatedQuoteDTO(
            instrument: $quote->instrument,
            bestBid: $quote,
            bestAsk: $quote,
            providers: [$quote],
            timestamp: $quote->timestamp,
        );
    }

    private function quote(
        string $instrument,
        int $providerMarketId,
        float $price,
        bool $reference = false,
        ?int $timestamp = null,
    ): QuoteDTO {
        return new QuoteDTO(
            instrument: $instrument,
            bid: $price - 0.5,
            ask: $price + 0.5,
            last: $price,
            provider: $reference ? 'tgju' : 'nobitex',
            volume: null,
            timestamp: $timestamp ?? now()->getTimestampMs(),
            providerMarketId: $providerMarketId,
            isReference: $reference,
        );
    }
}
