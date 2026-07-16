<?php

namespace Tests\Feature;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Asset\Models\Instrument;
use App\Domain\Market\Application\Jobs\SendPriceAlertNotificationJob;
use App\Domain\Market\Application\Jobs\SendPriceAlertPushJob;
use App\Domain\Market\Infrastructure\Persistence\Models\PriceAlert;
use App\Domain\Market\Infrastructure\Persistence\Models\PriceAlertNotificationDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackofficeAlertDeliveryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_administrators_cannot_access_backoffice_delivery_history_or_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/market/alert-deliveries')
            ->assertForbidden()
            ->assertJsonPath('message', 'Administrator access is required.');

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'backoffice-web',
        ])->assertForbidden();
    }

    public function test_administrators_can_list_and_retry_failed_deliveries(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $delivery = $this->failedDelivery();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/market/alert-deliveries?status=failed')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $delivery->id)
            ->assertJsonPath('data.0.instrument', 'USDT-IRT')
            ->assertJsonPath('data.0.push_status', 'failed')
            ->assertJsonPath('data.0.user.email', 'alert-owner@example.test');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/market/alert-deliveries/{$delivery->id}/retry")
            ->assertOk()
            ->assertJsonPath('data.push_status', 'pending')
            ->assertJsonPath('data.push_error', null);

        $this->assertSame('pending', $delivery->fresh()->push_status);
        Queue::assertPushed(SendPriceAlertNotificationJob::class);
    }

    public function test_administrators_retry_only_failed_provider_targets(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $delivery = $this->failedDelivery();
        $user = $delivery->event->alert->user;
        $device = $user->pushDevices()->create([
            'installation_id' => (string) Str::uuid(),
            'platform' => 'ios',
            'provider' => 'fcm',
            'provider_token' => 'failed-ios-token',
            'token_hash' => hash('sha256', 'failed-ios-token'),
            'enabled' => true,
            'last_seen_at' => now(),
        ]);
        $target = $delivery->pushDeliveries()->create([
            'push_device_id' => $device->id,
            'platform' => 'ios',
            'provider' => 'fcm',
            'provider_target' => 'failed-ios-token',
            'target_hash' => hash('sha256', 'failed-ios-token'),
            'status' => 'failed',
            'attempts' => 3,
            'error' => 'FCM timed out.',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/market/alert-deliveries/{$delivery->id}/retry")
            ->assertOk()
            ->assertJsonPath('data.push_status', 'pending')
            ->assertJsonPath('data.targets.0.id', $target->id)
            ->assertJsonPath('data.targets.0.platform', 'ios')
            ->assertJsonPath('data.targets.0.provider', 'fcm')
            ->assertJsonPath('data.targets.0.status', 'pending');

        $this->assertSame('pending', $target->refresh()->status);
        Queue::assertPushed(SendPriceAlertPushJob::class, 1);
        Queue::assertNotPushed(SendPriceAlertNotificationJob::class);
    }

    private function failedDelivery(): PriceAlertNotificationDelivery
    {
        $user = User::factory()->create(['email' => 'alert-owner@example.test']);
        $base = Asset::query()->create(['symbol' => 'USDT', 'name' => 'Tether', 'type' => 'crypto']);
        $quote = Asset::query()->create(['symbol' => 'IRT', 'name' => 'Iranian Toman', 'type' => 'fiat']);
        $instrument = Instrument::query()->create([
            'base_asset_id' => $base->id,
            'quote_asset_id' => $quote->id,
            'symbol' => 'USDT-IRT',
        ]);
        $alert = PriceAlert::query()->create([
            'user_id' => $user->id,
            'instrument_id' => $instrument->id,
            'scope' => 'best_market',
            'condition' => 'goes_above',
            'target_price' => 92000,
            'status' => 'triggered',
            'repeat' => 'once',
            'notify_push' => true,
            'notify_in_app' => true,
        ]);
        $event = $alert->events()->create([
            'type' => 'triggered',
            'payload' => ['price' => 92100, 'provider' => 'nobitex'],
            'occurred_at' => now(),
        ]);

        return PriceAlertNotificationDelivery::query()->create([
            'price_alert_event_id' => $event->id,
            'provider' => 'pushe',
            'push_status' => 'failed',
            'push_attempts' => 3,
            'push_error' => 'Pushe timed out.',
        ]);
    }
}
