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

        // Pushe targets an account group, so its most recently active Android
        // installation with a known language determines the group's language.
        $android = $active->where('platform', 'android')->where('provider', 'pushe')
            ->sortByDesc('last_seen_at');
        if ($android->isNotEmpty()) {
            $targets[] = new PushNotificationTarget(
                provider: 'pushe',
                platform: 'android',
                address: PriceAlertNotificationService::recipientId((int) $user->id),
                locale: $this->locale($android->first(fn ($device) => filled($device->locale))?->locale),
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
                locale: $this->locale($device->locale),
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

    private function locale(?string $locale): string
    {
        return filled($locale) ? strtolower(str_replace('_', '-', trim($locale))) : 'en';
    }
}
