<?php

namespace App\Domain\Market\Application\Services;

use App\Domain\Market\Application\DTO\PushNotificationTarget;
use App\Models\User;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

class PushNotificationTargetResolver
{
    public function __construct(private readonly ConfigRepository $config) {}

    /** @return list<PushNotificationTarget> */
    public function forUser(User $user): array
    {
        $devices = $user->pushDevices()->get();
        $active = $devices->where('enabled', true);
        $targets = [];

        if ($active->contains(fn ($device): bool => $device->platform === 'android' && $device->provider === 'pushe')) {
            $targets[] = new PushNotificationTarget(
                provider: 'pushe',
                platform: 'android',
                address: PriceAlertNotificationService::recipientId((int) $user->id),
            );
        }

        foreach ($active as $device) {
            if ($device->platform !== 'ios' || $device->provider !== 'fcm') {
                continue;
            }

            $token = $device->provider_token;
            if (! is_string($token) || $token === '') {
                continue;
            }

            $targets[] = new PushNotificationTarget(
                provider: 'fcm',
                platform: 'ios',
                address: $token,
                pushDeviceId: (int) $device->id,
            );
        }

        if ($devices->isEmpty() && $this->config->get('services.pushe.legacy_user_targeting', true)) {
            $targets[] = new PushNotificationTarget(
                provider: 'pushe',
                platform: 'android',
                address: PriceAlertNotificationService::recipientId((int) $user->id),
            );
        }

        return $targets;
    }
}
