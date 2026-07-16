<?php

namespace Tests\Feature;

use App\Domain\Market\Application\DTO\PushNotificationMessage;
use App\Domain\Market\Application\DTO\PushNotificationTarget;
use App\Domain\Market\Infrastructure\Notifications\PushePushNotificationSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushePushNotificationSenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_a_transactional_push_to_the_polsho_custom_id(): void
    {
        config()->set('services.pushe.enabled', true);
        config()->set('services.pushe.app_id', 'pushe-app');
        config()->set('services.pushe.token', 'pushe-token');
        config()->set('services.pushe.base_url', 'https://api.pushe.test');
        Http::fake(['https://api.pushe.test/*' => Http::response(['id' => 'delivery-1'])]);

        $result = app(PushePushNotificationSender::class)->send(
            new PushNotificationTarget('pushe', 'android', 'polsho-user-42'),
            new PushNotificationMessage(
                title: 'Price alert triggered',
                body: 'USDT-IRT reached 92,100.',
                data: ['price_alert_id' => 12],
                deepLink: 'polsho://alerts/12',
            ),
        );

        $this->assertSame('sent', $result->status);
        $this->assertSame('delivery-1', $result->providerMessageId);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.pushe.test/v2/messaging/rapid/'
                && $request->hasHeader('Authorization', 'Token pushe-token')
                && $request['app_id'] === 'pushe-app'
                && $request['custom_id'] === ['polsho-user-42']
                && $request['data']['title'] === 'Price alert triggered'
                && $request['data']['action']['action_type'] === 'U'
                && $request['data']['action']['url'] === 'polsho://alerts/12'
                && $request['data']['notif_channel_id'] === 'price_alerts'
                && $request['data']['show_foreground'] === false;
        });
    }

    public function test_skips_delivery_without_credentials(): void
    {
        config()->set('services.pushe.enabled', true);
        config()->set('services.pushe.app_id', null);
        config()->set('services.pushe.token', null);

        $result = app(PushePushNotificationSender::class)->send(
            new PushNotificationTarget('pushe', 'android', 'polsho-user-42'),
            new PushNotificationMessage(
                title: 'Price alert triggered',
                body: 'USDT-IRT reached 92,100.',
                data: [],
                deepLink: 'polsho://alerts/12',
            ),
        );

        $this->assertSame('skipped', $result->status);
    }
}
