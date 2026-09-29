<?php

namespace Tests\Feature;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Asset\Models\Instrument;
use App\Domain\Market\Application\DTO\PushNotificationDeliveryResult;
use App\Domain\Market\Application\DTO\PushNotificationMessage;
use App\Domain\Market\Application\DTO\PushNotificationTarget;
use App\Domain\Market\Application\Milestones\FanoutMilestoneJob;
use App\Domain\Market\Application\Milestones\MilestoneDelivery;
use App\Domain\Market\Application\Milestones\MilestoneEvaluator;
use App\Domain\Market\Application\Milestones\MilestoneEvent;
use App\Domain\Market\Application\Milestones\MilestoneRule;
use App\Domain\Market\Application\Milestones\SendMilestoneJob;
use App\Domain\Market\Application\Services\PushNotificationTargetResolver;
use App\Domain\Market\Application\Services\PushProviderRegistry;
use App\Domain\Market\Contracts\PushNotificationProvider;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Infrastructure\Persistence\Models\ProviderMarket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MarketMilestoneTest extends TestCase
{
    use RefreshDatabase;

    private MilestoneRule $rule;

    private Instrument $instrument;

    private ProviderMarket $market;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->freezeTime();
        $base = Asset::create(['symbol' => 'USDT', 'name' => 'Tether', 'type' => 'crypto']);
        $quote = Asset::create(['symbol' => 'IRT', 'name' => 'Toman', 'type' => 'fiat']);
        $this->instrument = Instrument::create(['symbol' => 'USDT-IRT', 'base_asset_id' => $base->id, 'quote_asset_id' => $quote->id, 'status' => 'active']);
        $provider = MarketProvider::create(['name' => 'Nobitex', 'slug' => 'nobitex', 'driver' => 'nobitex', 'base_url' => 'https://example.test', 'status' => 'active']);
        $this->market = ProviderMarket::create(['instrument_id' => $this->instrument->id, 'provider_id' => $provider->id, 'remote_symbol' => 'USDTIRT', 'status' => 'active']);
        $this->rule = MilestoneRule::create(['instrument_id' => $this->instrument->id, 'provider_market_id' => null, 'price_source' => 'best_market', 'enabled' => true, 'step' => '1000', 'confirmation_quotes' => 2, 'cooldown_seconds' => 300, 'direction' => 'both']);
    }

    private function tick(string $price, ?int $timestamp = null): void
    {
        if ($timestamp === null) {
            $this->travel(10)->seconds();
        }
        app(MilestoneEvaluator::class)->evaluate(['instrument' => 'USDT-IRT', 'best_ask' => ['provider_market_id' => $this->market->id, 'ask' => $price, 'timestamp' => $timestamp ?? now()->getTimestampMs()]]);
    }

    public function test_round_levels_confirm_then_reverse_without_boundary_spam(): void
    {
        $this->tick('253678');
        $this->assertDatabaseCount('market_milestone_events', 0);
        $this->tick('254020');
        $this->assertDatabaseCount('market_milestone_events', 0);
        $this->tick('254010');
        $this->tick('253990');
        $this->tick('254020');
        $this->tick('253000');
        $this->tick('252990');
        $events = MilestoneEvent::orderBy('id')->get();
        $this->assertSame(['254000.00000000', '253000.00000000'], $events->pluck('level')->all());
        $this->assertSame(['up', 'down'], $events->pluck('direction')->all());
    }

    public function test_skipped_levels_coalesce_and_out_of_order_quotes_do_not_confirm(): void
    {
        $this->tick('253678');
        $this->tick('256200');
        $this->tick('256200', now()->getTimestampMs());
        $this->tick('252000', now()->subSeconds(5)->getTimestampMs());
        $this->assertDatabaseCount('market_milestone_events', 0);
        $this->tick('256250');
        $this->assertDatabaseCount('market_milestone_events', 1);
        $this->assertSame('256000.00000000', MilestoneEvent::first()->level);
    }

    public function test_stale_future_gap_and_paused_quotes_do_not_broadcast(): void
    {
        $this->tick('253678');
        $this->tick('260000', now()->subMinutes(2)->getTimestampMs());
        $this->tick('260000', now()->addMinute()->getTimestampMs());
        $this->travel(2)->minutes();
        $this->tick('260000');
        $this->tick('260010');
        $this->rule->update(['enabled' => false]);
        $this->tick('261000');
        $this->tick('261010');
        $this->assertDatabaseCount('market_milestone_events', 0);
    }

    public function test_cooldown_does_not_block_other_levels_and_decimal_steps_are_exact(): void
    {
        $this->rule->update(['step' => '0.1']);
        foreach (['1.05', '1.10', '1.11', '1.00', '0.99', '1.10', '1.11', '1.20', '1.21'] as $price) {
            $this->tick($price);
        }
        $this->assertSame(['1.10000000', '1.00000000', '1.20000000'], MilestoneEvent::orderBy('id')->pluck('level')->all());
    }

    public function test_rejected_candidate_is_reset_and_direction_filter_keeps_tracking(): void
    {
        $this->rule->update(['direction' => 'up']);
        foreach (['253678', '254010', '253900', '254020'] as $price) {
            $this->tick($price);
        }
        $this->assertDatabaseCount('market_milestone_events', 0);
        foreach (['254030', '253000', '252990', '254000', '254010'] as $price) {
            $this->tick($price);
        }
        $this->assertDatabaseCount('market_milestone_events', 1); // The second up is in cooldown.
        $this->travel(6)->minutes();
        foreach (['253500', '254010', '254020'] as $price) {
            $this->tick($price);
        }
        $this->assertDatabaseCount('market_milestone_events', 2);
    }

    public function test_admin_validation_preview_and_reconfiguration_reset(): void
    {
        $url = '/api/backoffice/instruments/'.$this->instrument->id.'/milestones';
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'sanctum');
        $this->tick('253678');
        $this->getJson($url)->assertOk()->assertJsonPath('data.rule.prices.0.next_up', '254000.00000000')->assertJsonPath('data.rule.prices.0.next_down', '253000.00000000');
        $data = ['enabled' => false, 'step' => '1000', 'direction' => 'both', 'confirmation_quotes' => 2, 'cooldown_seconds' => 300];
        $this->putJson($url, [...$data, 'step' => '0'])->assertUnprocessable();
        $this->putJson($url, [...$data, 'step' => '0.000000001'])->assertUnprocessable();
        $this->putJson($url, $data)->assertOk()->assertJsonPath('data.state', null)->assertJsonPath('data.revision', 2);
        $this->putJson($url, $data)->assertOk()->assertJsonPath('data.revision', 2);
    }

    private function deviceUser(): User
    {
        $user = User::factory()->create();
        $user->pushDevices()->create(['installation_id' => 'installation-'.$user->id, 'platform' => 'ios', 'provider' => 'fcm', 'provider_token' => 'token-'.$user->id,
            'token_hash' => hash('sha256', 'token-'.$user->id), 'enabled' => true, 'locale' => 'fa', 'last_seen_at' => now()]);

        return $user;
    }

    private function event(): MilestoneEvent
    {
        foreach (['253678', '254010', '254020'] as $price) {
            $this->tick($price);
        }

        return MilestoneEvent::firstOrFail();
    }

    public function test_fanout_is_resumable_and_sending_is_deduplicated(): void
    {
        $this->deviceUser();
        $event = $this->event();
        $this->deviceUser(); // Registered after the event: outside audience snapshot.
        $job = new FanoutMilestoneJob($event->id);
        $job->handle(app(PushNotificationTargetResolver::class));
        $job->handle(app(PushNotificationTargetResolver::class));
        $this->assertDatabaseCount('market_milestone_deliveries', 1);
        $provider = $this->provider();
        $send = new SendMilestoneJob(MilestoneDelivery::first()->id);
        $registry = new PushProviderRegistry([$provider]);
        $send->handle($registry, app(PushNotificationTargetResolver::class));
        $send->handle($registry, app(PushNotificationTargetResolver::class));
        $this->assertSame(1, $provider->calls);
        $this->assertSame('sent', MilestoneDelivery::first()->status);
        $this->assertStringContainsString('۲۵۴,۰۰۰', $provider->message->title);
        $this->assertSame($event->expires_at->timestamp, $provider->message->expiresAt);
    }

    public function test_delivery_rechecks_eligibility_and_rule_revision(): void
    {
        $user = $this->deviceUser();
        $event = $this->event();
        (new FanoutMilestoneJob($event->id))->handle(app(PushNotificationTargetResolver::class));
        $user->pushDevices()->update(['enabled' => false]);
        $provider = $this->provider();
        (new SendMilestoneJob(MilestoneDelivery::first()->id))->handle(new PushProviderRegistry([$provider]), app(PushNotificationTargetResolver::class));
        $this->assertSame(0, $provider->calls);
        $this->assertSame('skipped', MilestoneDelivery::first()->status);
    }

    public function test_transient_failure_retries_and_expiry_stops_old_pushes(): void
    {
        $this->deviceUser();
        $event = $this->event();
        (new FanoutMilestoneJob($event->id))->handle(app(PushNotificationTargetResolver::class));
        $provider = $this->provider();
        $provider->fail = true;
        $job = new SendMilestoneJob(MilestoneDelivery::first()->id);
        $registry = new PushProviderRegistry([$provider]);
        $job->handle($registry, app(PushNotificationTargetResolver::class));
        $job->handle($registry, app(PushNotificationTargetResolver::class));
        $this->assertSame(1, $provider->calls);
        $this->assertSame('pending', MilestoneDelivery::first()->status);
        $this->travel(6)->minutes();
        $job->handle($registry, app(PushNotificationTargetResolver::class));
        $this->assertSame(1, $provider->calls);
        $this->assertSame('skipped', MilestoneDelivery::first()->status);
    }

    public function test_quotes_queued_before_rule_activation_cannot_set_the_baseline(): void
    {
        $this->rule->update(['configured_at' => now()->getTimestampMs()]);
        $this->tick('253678', now()->subSeconds(10)->getTimestampMs());
        $this->assertNull($this->rule->fresh()->state);
        $this->tick('254020');
        $this->assertSame('254020.00000000', $this->rule->fresh()->state['best_buy']['anchor']);
        $this->assertDatabaseCount('market_milestone_events', 0);
    }

    public function test_rule_edits_cancel_prepared_deliveries_and_outbox_recovers_pending_work(): void
    {
        $this->deviceUser();
        $event = $this->event();
        $this->artisan('market:dispatch-milestones')->assertSuccessful();
        Queue::assertPushed(FanoutMilestoneJob::class);
        (new FanoutMilestoneJob($event->id))->handle(app(PushNotificationTargetResolver::class));
        $this->rule->increment('revision');
        $provider = $this->provider();
        (new SendMilestoneJob(MilestoneDelivery::first()->id))->handle(new PushProviderRegistry([$provider]), app(PushNotificationTargetResolver::class));
        $this->assertSame('skipped', MilestoneDelivery::first()->status);
        $this->assertSame(0, $provider->calls);
    }

    public function test_expired_worker_lease_is_recovered_and_retry_can_succeed(): void
    {
        $this->deviceUser();
        $event = $this->event();
        (new FanoutMilestoneJob($event->id))->handle(app(PushNotificationTargetResolver::class));
        $delivery = MilestoneDelivery::first();
        $delivery->update(['status' => 'sending', 'attempts' => 1, 'lease_until' => now()->subSecond()]);
        $this->artisan('market:dispatch-milestones')->assertSuccessful();
        Queue::assertPushed(SendMilestoneJob::class);
        $provider = $this->provider();
        (new SendMilestoneJob($delivery->id))->handle(new PushProviderRegistry([$provider]), app(PushNotificationTargetResolver::class));
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertSame(2, $delivery->fresh()->attempts);
    }

    public function test_disabled_or_reference_source_is_ignored(): void
    {
        $this->tick('253678');
        $this->market->provider->update(['config' => ['is_reference' => true]]);
        $this->tick('254010');
        $this->tick('254020');
        $this->market->provider->update(['config' => [], 'status' => 'inactive']);
        $this->tick('254010');
        $this->tick('254020');
        $this->assertDatabaseCount('market_milestone_events', 0);
    }

    public function test_fast_rally_does_not_require_settling_in_one_bucket(): void
    {
        foreach (['253678', '254020', '255020', '256020', '257020'] as $price) {
            $this->tick($price);
        }
        $this->assertSame(['255000.00000000', '257000.00000000'], MilestoneEvent::orderBy('id')->pluck('level')->all());
    }

    public function test_best_buy_uses_lowest_ask_and_follows_a_new_winning_exchange(): void
    {
        $otherProvider = MarketProvider::create(['name' => 'Wallex', 'slug' => 'wallex', 'driver' => 'wallex', 'base_url' => 'https://example.test', 'status' => 'active']);
        $other = ProviderMarket::create(['provider_id' => $otherProvider->id, 'instrument_id' => $this->instrument->id, 'remote_symbol' => 'USDTIRT', 'status' => 'active']);
        $this->bestTick('best_ask', '253678', $this->market);
        $this->bestTick('best_ask', '254010', $other, ageSeconds: 15);
        $this->bestTick('best_ask', '254020', $other);
        $event = MilestoneEvent::firstOrFail();
        $this->assertSame('254020.00000000', $event->price);
        $this->assertSame('best_buy', $event->price_source);
        $this->assertSame('wallex', $event->provider);
        $this->assertSame('Wallex', $event->provider_name);
        $this->assertSame($other->id, $event->provider_market_id);
    }

    public function test_best_sell_uses_highest_bid_without_last_or_ask_fallback(): void
    {
        foreach (['254200', '253000', '252990'] as $price) {
            $this->bestTick('best_bid', $price, $this->market);
        }
        $event = MilestoneEvent::firstOrFail();
        $this->assertSame('253000.00000000', $event->level);
        $this->assertSame('252990.00000000', $event->price);
        $this->assertSame('down', $event->direction);
        $this->assertSame('best_sell', $event->price_source);
    }

    public function test_cached_winner_does_not_confirm_when_other_exchange_updates(): void
    {
        $this->bestTick('best_ask', '253678', $this->market);
        $this->bestTick('best_ask', '254010', $this->market);
        $this->bestTick('best_ask', '254010', $this->market, ageSeconds: 10);
        $this->assertDatabaseCount('market_milestone_events', 0);
        $this->bestTick('best_ask', '254010', $this->market);
        $this->assertDatabaseCount('market_milestone_events', 1);
    }

    private function bestTick(string $side, string $price, ProviderMarket $market, int $ageSeconds = 0): void
    {
        $this->travel(10)->seconds();
        app(MilestoneEvaluator::class)->evaluate(['instrument' => 'USDT-IRT', 'timestamp' => now()->getTimestampMs(),
            $side => ['provider_market_id' => $market->id, 'provider' => $market->provider->slug,
                'ask' => $side === 'best_ask' ? $price : '800000',
                'bid' => $side === 'best_bid' ? $price : '100000', 'last' => '999999',
                'timestamp' => now()->subSeconds($ageSeconds)->getTimestampMs()],
            // Aggregator already filters outliers; never reselect from this raw list.
            'providers' => [['provider_market_id' => $market->id, 'ask' => '1', 'bid' => '999999999']],
        ]);
    }

    public function test_best_price_rule_does_not_require_a_fixed_exchange(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'sanctum');
        $url = '/api/backoffice/instruments/'.$this->instrument->id.'/milestones';
        $data = ['enabled' => false, 'step' => '1000', 'direction' => 'both', 'confirmation_quotes' => 2, 'cooldown_seconds' => 300];
        $this->putJson($url, $data)->assertOk()->assertJsonPath('data.price_source', 'best_market')->assertJsonPath('data.provider_market_id', null);
        $this->getJson($url)->assertOk()->assertJsonPath('data.rule.prices.0.price_source', 'best_buy');
        $this->putJson($url, [...$data, 'price_source' => 'best_buy'])->assertUnprocessable();
        $this->putJson($url, [...$data, 'provider_market_id' => $this->market->id])->assertUnprocessable();
    }

    public function test_opt_out_is_default_on_persistent_and_account_scoped(): void
    {
        $this->getJson('/api/notification-preferences')->assertUnauthorized();
        $user = $this->deviceUser();
        $other = $this->deviceUser();
        $this->actingAs($user, 'sanctum')->getJson('/api/notification-preferences')->assertOk()->assertJsonPath('data.market_milestones_enabled', true);
        $this->patchJson('/api/notification-preferences', ['market_milestones_enabled' => false, 'user_id' => $other->id])
            ->assertOk()->assertJsonPath('data.market_milestones_enabled', false);
        $this->assertFalse($user->fresh()->market_milestones_enabled);
        $this->assertTrue($other->fresh()->market_milestones_enabled);
        $this->patchJson('/api/notification-preferences', ['market_milestones_enabled' => 'invalid'])->assertUnprocessable();
        $event = $this->event();
        (new FanoutMilestoneJob($event->id))->handle(app(PushNotificationTargetResolver::class));
        $this->assertSame([$other->id], MilestoneDelivery::pluck('user_id')->all());
        $this->actingAs($other, 'sanctum')->patchJson('/api/notification-preferences', ['market_milestones_enabled' => false])->assertOk();
        $provider = $this->provider();
        (new SendMilestoneJob(MilestoneDelivery::first()->id))->handle(new PushProviderRegistry([$provider]), app(PushNotificationTargetResolver::class));
        $this->assertSame(0, $provider->calls);
        $this->assertSame('skipped', MilestoneDelivery::first()->status);
    }

    public function test_best_buy_message_includes_side_and_source_price(): void
    {
        $this->deviceUser();
        foreach (['253678', '254010', '254020'] as $price) {
            $this->bestTick('best_ask', $price, $this->market);
        }
        $event = MilestoneEvent::firstOrFail();
        (new FanoutMilestoneJob($event->id))->handle(app(PushNotificationTargetResolver::class));
        $provider = $this->provider();
        (new SendMilestoneJob(MilestoneDelivery::first()->id))->handle(new PushProviderRegistry([$provider]), app(PushNotificationTargetResolver::class));
        $this->assertStringContainsString('بهترین قیمت خرید', $provider->message->title);
        $this->assertStringContainsString('Nobitex', $provider->message->body);
        $this->assertStringContainsString('۲۵۴,۰۲۰', $provider->message->body);
        $this->assertSame('best_buy', $provider->message->data['price_source']);
        $this->assertSame('254020.00000000', $provider->message->data['price']);
    }

    private function bothTick(string $ask, string $bid, int $bidAgeSeconds = 0): void
    {
        $this->travel(10)->seconds();
        app(MilestoneEvaluator::class)->evaluate([
            'instrument' => 'USDT-IRT', 'timestamp' => now()->getTimestampMs(),
            'best_ask' => ['provider_market_id' => $this->market->id, 'ask' => $ask, 'timestamp' => now()->getTimestampMs()],
            'best_bid' => ['provider_market_id' => $this->market->id, 'bid' => $bid, 'timestamp' => now()->subSeconds($bidAgeSeconds)->getTimestampMs()],
        ]);
    }

    public function test_simultaneous_buy_and_sell_crossing_sends_one_combined_notification(): void
    {
        $this->deviceUser();
        $this->bothTick('253678', '253600');
        $this->bothTick('254100', '254000');
        $this->bothTick('254120', '254020');
        $this->assertDatabaseCount('market_milestone_events', 1);
        $event = MilestoneEvent::firstOrFail();
        $this->assertSame(['best_buy', 'best_sell'], array_column($event->matches, 'price_source'));
        $this->assertSame(['254120.00000000', '254020.00000000'], array_column($event->matches, 'price'));
        (new FanoutMilestoneJob($event->id))->handle(app(PushNotificationTargetResolver::class));
        $this->assertDatabaseCount('market_milestone_deliveries', 1);
        $provider = $this->provider();
        (new SendMilestoneJob(MilestoneDelivery::first()->id))->handle(new PushProviderRegistry([$provider]), app(PushNotificationTargetResolver::class));
        $this->assertSame(1, $provider->calls);
        $this->assertStringContainsString('خرید', $provider->message->body);
        $this->assertStringContainsString('فروش', $provider->message->body);
        $this->assertStringContainsString('۲۵۴,۱۲۰', $provider->message->body);
        $this->assertStringContainsString('۲۵۴,۰۲۰', $provider->message->body);
        $this->assertCount(2, $provider->message->data['matches']);
    }

    public function test_different_levels_at_the_same_timestamp_preserve_both_events(): void
    {
        $this->bothTick('253678', '252600');
        $this->bothTick('254100', '253000');
        $this->bothTick('254120', '253020');
        $events = MilestoneEvent::orderBy('id')->get();
        $this->assertSame(['254000.00000000', '253000.00000000'], $events->pluck('level')->all());
        $this->assertSame($events[0]->quote_timestamp, $events[1]->quote_timestamp);
    }

    public function test_later_sell_confirmation_of_same_level_obeys_shared_cooldown(): void
    {
        $this->bothTick('253678', '253600');
        $this->bothTick('254100', '253800');
        $this->bothTick('254120', '253900');
        $this->bothTick('254140', '254000');
        $this->bothTick('254160', '254020');
        $this->assertDatabaseCount('market_milestone_events', 1);
        $this->assertSame('254000.00000000', $this->rule->fresh()->state['best_sell']['anchor']);
    }

    public function test_stale_sell_price_does_not_block_fresh_buy_price(): void
    {
        $this->bothTick('253678', '253600', 90);
        $this->bothTick('254100', '254000', 90);
        $this->bothTick('254120', '254020', 90);
        $this->assertDatabaseCount('market_milestone_events', 1);
        $this->assertSame('best_buy', MilestoneEvent::firstOrFail()->price_source);
        $this->assertArrayNotHasKey('best_sell', $this->rule->fresh()->state);
    }

    public function test_queued_milestone_uses_latest_device_language_at_send_time(): void
    {
        $user = $this->deviceUser();
        $user->pushDevices()->update(['locale' => 'en']);
        $event = $this->event();
        (new FanoutMilestoneJob($event->id))->handle(app(PushNotificationTargetResolver::class));
        $delivery = MilestoneDelivery::firstOrFail();
        $this->assertSame('en', $delivery->locale);
        $user->pushDevices()->update(['locale' => 'fa_IR']);
        $provider = $this->provider();
        (new SendMilestoneJob($delivery->id))->handle(new PushProviderRegistry([$provider]), app(PushNotificationTargetResolver::class));
        $this->assertSame('fa-ir', $delivery->fresh()->locale);
        $this->assertStringContainsString('بهترین قیمت خرید', $provider->message->title);
    }

    private function provider(): PushNotificationProvider
    {
        return new class implements PushNotificationProvider
        {
            public int $calls = 0;

            public bool $fail = false;

            public ?PushNotificationMessage $message = null;

            public function key(): string
            {
                return 'fcm';
            }

            public function send(PushNotificationTarget $target, PushNotificationMessage $message): PushNotificationDeliveryResult
            {
                $this->calls++;
                $this->message = $message;
                if ($this->fail) {
                    throw new \RuntimeException('Temporarily unavailable');
                }

                return PushNotificationDeliveryResult::sent('provider-id');
            }
        };
    }
}
