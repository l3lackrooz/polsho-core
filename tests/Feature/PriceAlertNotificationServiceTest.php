<?php

namespace Tests\Feature;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Asset\Models\Instrument;
use App\Domain\Market\Application\Jobs\SendPriceAlertPushJob;
use App\Domain\Market\Application\Services\PriceAlertNotificationService;
use App\Domain\Market\Infrastructure\Notifications\PriceAlertTriggeredNotification;
use App\Domain\Market\Infrastructure\Persistence\Models\PriceAlert;
use App\Domain\Market\Infrastructure\Persistence\Models\PriceAlertEvent;
use App\Domain\Market\Infrastructure\Persistence\Models\PriceAlertNotificationDelivery;
use App\Domain\Market\Infrastructure\Persistence\Models\PriceAlertPushDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class PriceAlertNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_stores_in_app_notification_and_queues_one_legacy_pushe_target(): void
    {
        Queue::fake();
        [$user, $event] = $this->triggeredEvent();

        app(PriceAlertNotificationService::class)->deliver($event->id);
        app(PriceAlertNotificationService::class)->deliver($event->id);

        $delivery = PriceAlertNotificationDelivery::query()->sole();
        $target = PriceAlertPushDelivery::query()->sole();

        $this->assertSame('pushe', $delivery->provider);
        $this->assertSame('pending', $delivery->push_status);
        $this->assertSame('pushe', $target->provider);
        $this->assertSame('android', $target->platform);
        $this->assertSame(PriceAlertNotificationService::recipientId($user->id), $target->provider_target);
        $this->assertNotNull($delivery->in_app_sent_at);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'type' => PriceAlertTriggeredNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
        ]);
        Queue::assertPushed(SendPriceAlertPushJob::class, 1);
    }

    public function test_creates_one_grouped_android_target_and_one_target_per_iphone(): void
    {
        Queue::fake();
        [$user, $event] = $this->triggeredEvent();

        $this->device($user, 'android', 'pushe');
        $this->device($user, 'android', 'pushe');
        $this->device($user, 'ios', 'fcm', 'ios-token-1');
        $this->device($user, 'ios', 'fcm', 'ios-token-2');

        app(PriceAlertNotificationService::class)->deliver($event->id);

        $delivery = PriceAlertNotificationDelivery::query()->sole();
        $this->assertSame('multiple', $delivery->provider);
        $this->assertSame('pending', $delivery->push_status);
        $this->assertSame(1, PriceAlertPushDelivery::query()->where('provider', 'pushe')->count());
        $this->assertSame(2, PriceAlertPushDelivery::query()->where('provider', 'fcm')->count());
        Queue::assertPushed(SendPriceAlertPushJob::class, 3);
    }

    public function test_skips_push_when_the_alert_opted_out_but_keeps_the_in_app_record(): void
    {
        Queue::fake();
        [, $event] = $this->triggeredEvent(notifyPush: false);

        app(PriceAlertNotificationService::class)->deliver($event->id);

        $delivery = PriceAlertNotificationDelivery::query()->sole();

        $this->assertSame('skipped', $delivery->push_status);
        $this->assertNotNull($delivery->in_app_sent_at);
        $this->assertDatabaseCount('price_alert_push_deliveries', 0);
        $this->assertDatabaseCount('notifications', 1);
        Queue::assertNothingPushed();
    }

    public function test_records_a_terminal_dispatch_failure_without_overwriting_sent_delivery(): void
    {
        [, $event] = $this->triggeredEvent();
        $service = app(PriceAlertNotificationService::class);
        $delivery = PriceAlertNotificationDelivery::query()->create([
            'price_alert_event_id' => $event->id,
            'provider' => 'multiple',
            'push_status' => 'pending',
        ]);

        $service->markDispatchFailed($event->id, new RuntimeException('Dispatch failed.'));

        $this->assertSame('failed', $delivery->refresh()->push_status);
        $this->assertSame('Dispatch failed.', $delivery->push_error);

        $delivery->update(['push_status' => 'sent', 'push_error' => null]);
        $service->markDispatchFailed($event->id, new RuntimeException('Should not replace sent.'));

        $this->assertSame('sent', $delivery->refresh()->push_status);
        $this->assertNull($delivery->push_error);
    }

    private function device(User $user, string $platform, string $provider, ?string $token = null): void
    {
        $user->pushDevices()->create([
            'installation_id' => (string) Str::uuid(),
            'platform' => $platform,
            'provider' => $provider,
            'provider_token' => $token,
            'token_hash' => $token === null ? null : hash('sha256', $token),
            'enabled' => true,
            'last_seen_at' => now(),
        ]);
    }

    /** @return array{User, PriceAlertEvent} */
    private function triggeredEvent(bool $notifyPush = true): array
    {
        $user = User::factory()->create();
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
            'notify_push' => $notifyPush,
            'notify_in_app' => true,
        ]);
        $event = $alert->events()->create([
            'type' => 'triggered',
            'payload' => ['price' => 92100, 'provider' => 'nobitex'],
            'occurred_at' => now(),
        ]);

        return [$user, $event];
    }
}
