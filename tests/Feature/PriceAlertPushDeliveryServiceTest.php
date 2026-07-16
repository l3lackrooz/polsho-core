<?php

namespace Tests\Feature;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Asset\Models\Instrument;
use App\Domain\Market\Application\DTO\PushNotificationDeliveryResult;
use App\Domain\Market\Application\DTO\PushNotificationMessage;
use App\Domain\Market\Application\DTO\PushNotificationTarget;
use App\Domain\Market\Application\Services\PriceAlertPushDeliveryService;
use App\Domain\Market\Application\Services\PushProviderRegistry;
use App\Domain\Market\Contracts\PushNotificationProvider;
use App\Domain\Market\Infrastructure\Persistence\Models\PriceAlert;
use App\Domain\Market\Infrastructure\Persistence\Models\PriceAlertPushDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PriceAlertPushDeliveryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_one_target_and_aggregates_the_parent_delivery(): void
    {
        $provider = new RecordingPushProvider(PushNotificationDeliveryResult::sent('fcm-message-1'));
        $this->app->instance(PushProviderRegistry::class, new PushProviderRegistry([$provider]));
        $delivery = $this->delivery();

        app(PriceAlertPushDeliveryService::class)->send($delivery->id);

        $this->assertSame('ios-token', $provider->target?->address);
        $this->assertSame('price_alert.triggered', $provider->message?->data['type']);
        $this->assertSame('/alerts/'.$delivery->notificationDelivery->event->alert->id, $provider->message?->data['route']);
        $this->assertSame('sent', $delivery->refresh()->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame('sent', $delivery->notificationDelivery->refresh()->push_status);
        $this->assertSame('fcm-message-1', $delivery->notificationDelivery->provider_message_id);
    }

    public function test_invalid_fcm_target_disables_the_registered_device(): void
    {
        $provider = new RecordingPushProvider(
            PushNotificationDeliveryResult::failed('FCM token is unregistered.', invalidTarget: true),
        );
        $this->app->instance(PushProviderRegistry::class, new PushProviderRegistry([$provider]));
        $delivery = $this->delivery();

        app(PriceAlertPushDeliveryService::class)->send($delivery->id);

        $this->assertSame('failed', $delivery->refresh()->status);
        $this->assertSame('failed', $delivery->notificationDelivery->refresh()->push_status);
        $this->assertFalse($delivery->pushDevice->refresh()->enabled);
        $this->assertNull($delivery->pushDevice->provider_token);
        $this->assertNotNull($delivery->pushDevice->invalidated_at);
    }

    private function delivery(): PriceAlertPushDelivery
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
            'notify_push' => true,
            'notify_in_app' => true,
        ]);
        $event = $alert->events()->create([
            'type' => 'triggered',
            'payload' => ['price' => 92100],
            'occurred_at' => now(),
        ]);
        $parent = $event->notificationDelivery()->create([
            'provider' => 'fcm',
            'push_status' => 'pending',
        ]);
        $device = $user->pushDevices()->create([
            'installation_id' => (string) Str::uuid(),
            'platform' => 'ios',
            'provider' => 'fcm',
            'provider_token' => 'ios-token',
            'token_hash' => hash('sha256', 'ios-token'),
            'enabled' => true,
            'last_seen_at' => now(),
        ]);

        return $parent->pushDeliveries()->create([
            'push_device_id' => $device->id,
            'platform' => 'ios',
            'provider' => 'fcm',
            'provider_target' => 'ios-token',
            'target_hash' => hash('sha256', 'ios-token'),
            'status' => 'pending',
        ]);
    }
}

class RecordingPushProvider implements PushNotificationProvider
{
    public ?PushNotificationTarget $target = null;

    public ?PushNotificationMessage $message = null;

    public function __construct(private readonly PushNotificationDeliveryResult $result) {}

    public function key(): string
    {
        return 'fcm';
    }

    public function send(
        PushNotificationTarget $target,
        PushNotificationMessage $message,
    ): PushNotificationDeliveryResult {
        $this->target = $target;
        $this->message = $message;

        return $this->result;
    }
}
