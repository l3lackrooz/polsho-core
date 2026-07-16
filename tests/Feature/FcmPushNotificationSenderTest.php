<?php

namespace Tests\Feature;

use App\Domain\Market\Application\DTO\PushNotificationMessage;
use App\Domain\Market\Application\DTO\PushNotificationTarget;
use App\Domain\Market\Contracts\FcmAccessTokenProvider;
use App\Domain\Market\Infrastructure\Notifications\FcmPushNotificationSender;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FcmPushNotificationSenderTest extends TestCase
{
    public function test_sends_an_ios_notification_with_the_canonical_alert_payload(): void
    {
        $this->app->instance(FcmAccessTokenProvider::class, new FakeFcmAccessTokenProvider);
        config()->set('services.fcm.enabled', true);
        config()->set('services.fcm.project_id', 'polsho-project');
        config()->set('services.fcm.base_url', 'https://fcm.test/v1');
        Http::fake([
            'https://fcm.test/*' => Http::response([
                'name' => 'projects/polsho-project/messages/message-1',
            ]),
        ]);

        $result = app(FcmPushNotificationSender::class)->send(
            new PushNotificationTarget('fcm', 'ios', 'ios-fcm-token', 9),
            new PushNotificationMessage(
                title: 'Price alert triggered',
                body: 'USDT-IRT reached 92,100.',
                data: [
                    'type' => 'price_alert.triggered',
                    'price_alert_id' => 12,
                    'schema_version' => 1,
                ],
                deepLink: 'polsho://alerts/12',
            ),
        );

        $this->assertSame('sent', $result->status);
        $this->assertSame('projects/polsho-project/messages/message-1', $result->providerMessageId);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://fcm.test/v1/projects/polsho-project/messages:send'
                && $request->hasHeader('Authorization', 'Bearer test-google-token')
                && $request['message']['token'] === 'ios-fcm-token'
                && $request['message']['notification']['title'] === 'Price alert triggered'
                && $request['message']['data']['price_alert_id'] === '12'
                && $request['message']['data']['schema_version'] === '1'
                && $request['message']['apns']['headers']['apns-priority'] === '10'
                && $request['message']['apns']['payload']['aps']['sound'] === 'default';
        });
    }

    public function test_marks_an_unregistered_fcm_token_as_an_invalid_target(): void
    {
        $this->app->instance(FcmAccessTokenProvider::class, new FakeFcmAccessTokenProvider);
        config()->set('services.fcm.enabled', true);
        config()->set('services.fcm.project_id', 'polsho-project');
        config()->set('services.fcm.base_url', 'https://fcm.test/v1');
        Http::fake([
            'https://fcm.test/*' => Http::response([
                'error' => [
                    'details' => [[
                        '@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError',
                        'errorCode' => 'UNREGISTERED',
                    ]],
                ],
            ], 404),
        ]);

        $result = app(FcmPushNotificationSender::class)->send(
            new PushNotificationTarget('fcm', 'ios', 'expired-token', 9),
            new PushNotificationMessage(
                title: 'Price alert triggered',
                body: 'USDT-IRT reached 92,100.',
                data: [],
                deepLink: 'polsho://alerts/12',
            ),
        );

        $this->assertSame('failed', $result->status);
        $this->assertTrue($result->invalidTarget);
    }
}

class FakeFcmAccessTokenProvider implements FcmAccessTokenProvider
{
    public function token(): string
    {
        return 'test-google-token';
    }
}
