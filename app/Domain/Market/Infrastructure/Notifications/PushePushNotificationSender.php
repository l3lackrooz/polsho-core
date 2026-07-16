<?php

namespace App\Domain\Market\Infrastructure\Notifications;

use App\Domain\Market\Application\DTO\PushNotificationDeliveryResult;
use App\Domain\Market\Application\DTO\PushNotificationMessage;
use App\Domain\Market\Application\DTO\PushNotificationTarget;
use App\Domain\Market\Contracts\PushNotificationProvider;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use InvalidArgumentException;
use RuntimeException;

class PushePushNotificationSender implements PushNotificationProvider
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly ConfigRepository $config,
    ) {}

    public function key(): string
    {
        return 'pushe';
    }

    public function send(
        PushNotificationTarget $target,
        PushNotificationMessage $message,
    ): PushNotificationDeliveryResult {
        if ($target->provider !== $this->key() || $target->platform !== 'android') {
            throw new InvalidArgumentException('Pushe requires an Android Pushe target.');
        }

        if (! $this->config->get('services.pushe.enabled')) {
            return PushNotificationDeliveryResult::skipped('Pushe delivery is disabled.');
        }

        $appId = (string) $this->config->get('services.pushe.app_id');
        $token = (string) $this->config->get('services.pushe.token');
        if ($appId === '' || $token === '') {
            return PushNotificationDeliveryResult::skipped('Pushe credentials are not configured.');
        }

        $response = $this->http
            ->acceptJson()
            ->withToken($token, 'Token')
            ->timeout((int) $this->config->get('services.pushe.timeout', 10))
            ->post(rtrim((string) $this->config->get('services.pushe.base_url'), '/').'/v2/messaging/rapid/', [
                'app_id' => $appId,
                'custom_id' => [$target->address],
                'data' => [
                    'title' => $message->title,
                    'content' => $message->body,
                    'action' => [
                        'action_type' => 'U',
                        'url' => $message->deepLink,
                    ],
                    'notif_channel_id' => 'price_alerts',
                    // Flutter refreshes its own in-app inbox while it is open.
                    'show_foreground' => false,
                ],
                'priority' => 3,
                'time_to_live' => 3600,
            ]);

        if ($response->successful()) {
            $providerMessageId = data_get($response->json(), 'id');

            return PushNotificationDeliveryResult::sent(
                is_scalar($providerMessageId) ? (string) $providerMessageId : null,
            );
        }

        $error = sprintf('Pushe returned HTTP %d: %s', $response->status(), $response->body());

        if ($response->serverError() || $response->status() === 429) {
            throw new RuntimeException($error);
        }

        return PushNotificationDeliveryResult::failed($error);
    }
}
