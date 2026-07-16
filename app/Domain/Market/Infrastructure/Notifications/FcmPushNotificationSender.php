<?php

namespace App\Domain\Market\Infrastructure\Notifications;

use App\Domain\Market\Application\DTO\PushNotificationDeliveryResult;
use App\Domain\Market\Application\DTO\PushNotificationMessage;
use App\Domain\Market\Application\DTO\PushNotificationTarget;
use App\Domain\Market\Contracts\FcmAccessTokenProvider;
use App\Domain\Market\Contracts\PushNotificationProvider;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use InvalidArgumentException;
use RuntimeException;

class FcmPushNotificationSender implements PushNotificationProvider
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly ConfigRepository $config,
        private readonly FcmAccessTokenProvider $tokens,
    ) {}

    public function key(): string
    {
        return 'fcm';
    }

    public function send(
        PushNotificationTarget $target,
        PushNotificationMessage $message,
    ): PushNotificationDeliveryResult {
        if ($target->provider !== $this->key() || $target->platform !== 'ios') {
            throw new InvalidArgumentException('FCM requires an iOS FCM target.');
        }

        if (! $this->config->get('services.fcm.enabled')) {
            return PushNotificationDeliveryResult::skipped('FCM delivery is disabled.');
        }

        $projectId = (string) $this->config->get('services.fcm.project_id', '');
        if ($projectId === '') {
            return PushNotificationDeliveryResult::skipped('The Firebase project ID is not configured.');
        }

        $baseUrl = rtrim((string) $this->config->get('services.fcm.base_url'), '/');
        $response = $this->http
            ->acceptJson()
            ->withToken($this->tokens->token())
            ->timeout((int) $this->config->get('services.fcm.timeout', 10))
            ->post("{$baseUrl}/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $target->address,
                    'notification' => [
                        'title' => $message->title,
                        'body' => $message->body,
                    ],
                    'data' => $message->stringData(),
                    'apns' => [
                        'headers' => ['apns-priority' => '10'],
                        'payload' => [
                            'aps' => ['sound' => 'default'],
                        ],
                    ],
                ],
            ]);

        if ($response->successful()) {
            $messageId = data_get($response->json(), 'name');

            return PushNotificationDeliveryResult::sent(
                is_scalar($messageId) ? (string) $messageId : null,
            );
        }

        $error = sprintf('FCM returned HTTP %d: %s', $response->status(), $response->body());
        if ($response->serverError() || $response->status() === 429) {
            throw new RuntimeException($error);
        }

        return PushNotificationDeliveryResult::failed(
            $error,
            invalidTarget: $this->errorCode($response->json()) === 'UNREGISTERED',
        );
    }

    private function errorCode(mixed $payload): ?string
    {
        $details = data_get($payload, 'error.details', []);
        if (! is_array($details)) {
            return null;
        }

        foreach ($details as $detail) {
            if (is_array($detail) && isset($detail['errorCode']) && is_string($detail['errorCode'])) {
                return $detail['errorCode'];
            }
        }

        return null;
    }
}
