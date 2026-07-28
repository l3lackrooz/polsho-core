<?php

namespace App\Domain\Market\Infrastructure\Notifications;

use App\Domain\Market\Application\DTO\PushNotificationDeliveryResult;
use App\Domain\Market\Application\DTO\PushNotificationMessage;
use App\Domain\Market\Application\DTO\PushNotificationTarget;
use App\Domain\Market\Contracts\FcmAccessTokenProvider;
use App\Domain\Market\Contracts\PushNotificationProvider;
use App\Domain\Market\Infrastructure\Persistence\Models\LiveActivityPushToken;
use App\Domain\Market\Infrastructure\Persistence\Models\PushDevice;
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
        $fcmMessage = [
            'token' => $target->address,
            'data' => $message->stringData(),
        ];

        if ($target->liveActivityUpdateToken !== null && $message->liveActivityStart !== null) {
            $fcmMessage['apns'] = [
                'live_activity_token' => $target->liveActivityUpdateToken,
                'headers' => ['apns-priority' => '10'],
                'payload' => [
                    'aps' => [
                        'event' => 'update',
                        'timestamp' => $message->liveActivityStart['timestamp'],
                        'content-state' => $message->liveActivityStart['content_state'],
                        'alert' => ['title' => $message->title, 'body' => $message->body],
                        'sound' => 'default',
                    ],
                ],
            ];
        } elseif ($target->liveActivityPushToStartToken !== null && $message->liveActivityStart !== null) {
            $fcmMessage['apns'] = [
                'live_activity_token' => $target->liveActivityPushToStartToken,
                'headers' => ['apns-priority' => '10'],
                'payload' => [
                    'aps' => [
                        'event' => 'start',
                        'timestamp' => $message->liveActivityStart['timestamp'],
                        'content-state' => $message->liveActivityStart['content_state'],
                        'attributes-type' => 'PolshoAlertAttributes',
                        'attributes' => $message->liveActivityStart['attributes'],
                        'alert' => ['title' => $message->title, 'body' => $message->body],
                        'sound' => 'default',
                    ],
                ],
            ];
        } else {
            $fcmMessage['notification'] = [
                'title' => $message->title,
                'body' => $message->body,
            ];
            $fcmMessage['apns'] = [
                'headers' => ['apns-priority' => '10'],
                'payload' => [
                    'aps' => ['sound' => 'default'],
                ],
            ];
        }

        $response = $this->http
            ->acceptJson()
            ->withToken($this->tokens->token())
            ->timeout((int) $this->config->get('services.fcm.timeout', 10))
            ->post("{$baseUrl}/projects/{$projectId}/messages:send", [
                'message' => $fcmMessage,
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

    /** @param array<string, mixed> $contentState */
    public function endLiveActivity(PushDevice $device, LiveActivityPushToken $activity, array $contentState): PushNotificationDeliveryResult
    {
        $deviceToken = $device->provider_token;
        if (! is_string($deviceToken) || $deviceToken === '' || ! $this->config->get('services.fcm.enabled')) return PushNotificationDeliveryResult::skipped('FCM Live Activity delivery is unavailable.');
        $projectId = (string) $this->config->get('services.fcm.project_id', '');
        if ($projectId === '') return PushNotificationDeliveryResult::skipped('The Firebase project ID is not configured.');
        $baseUrl = rtrim((string) $this->config->get('services.fcm.base_url'), '/');
        $response = $this->http->acceptJson()->withToken($this->tokens->token())->timeout((int) $this->config->get('services.fcm.timeout', 10))->post("{$baseUrl}/projects/{$projectId}/messages:send", ['message' => ['token' => $deviceToken, 'apns' => ['live_activity_token' => $activity->token, 'headers' => ['apns-priority' => '10'], 'payload' => ['aps' => ['event' => 'end', 'timestamp' => now()->getTimestamp(), 'content-state' => $contentState, 'dismissal-date' => now()->getTimestamp()]]]]]);
        if ($response->successful()) return PushNotificationDeliveryResult::sent((string) data_get($response->json(), 'name'));
        return PushNotificationDeliveryResult::failed(sprintf('FCM returned HTTP %d: %s', $response->status(), $response->body()), $this->errorCode($response->json()) === 'UNREGISTERED');
    }

    /** @param array<string, mixed> $contentState */
    public function updateLiveActivity(PushDevice $device, LiveActivityPushToken $activity, array $contentState, int $timestamp): PushNotificationDeliveryResult
    {
        $deviceToken = $device->provider_token;
        if (! is_string($deviceToken) || $deviceToken === '' || ! $this->config->get('services.fcm.enabled')) return PushNotificationDeliveryResult::skipped('FCM Live Activity delivery is unavailable.');
        $projectId = (string) $this->config->get('services.fcm.project_id', '');
        if ($projectId === '') return PushNotificationDeliveryResult::skipped('The Firebase project ID is not configured.');
        $baseUrl = rtrim((string) $this->config->get('services.fcm.base_url'), '/');
        $response = $this->http->acceptJson()->withToken($this->tokens->token())->timeout((int) $this->config->get('services.fcm.timeout', 10))->post("{$baseUrl}/projects/{$projectId}/messages:send", ['message' => ['token' => $deviceToken, 'apns' => ['live_activity_token' => $activity->token, 'headers' => ['apns-priority' => '10'], 'payload' => ['aps' => ['event' => 'update', 'timestamp' => $timestamp, 'content-state' => $contentState]]]]]);
        if ($response->successful()) return PushNotificationDeliveryResult::sent((string) data_get($response->json(), 'name'));
        return PushNotificationDeliveryResult::failed(sprintf('FCM returned HTTP %d: %s', $response->status(), $response->body()), $this->errorCode($response->json()) === 'UNREGISTERED');
    }
}
