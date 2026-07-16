<?php

namespace App\Domain\Market\Application\Presenters;

use App\Domain\Market\Infrastructure\Persistence\Models\PushDevice;

class PushDevicePresenter
{
    /** @return array<string, mixed> */
    public function present(PushDevice $device): array
    {
        return [
            'installation_id' => $device->installation_id,
            'platform' => $device->platform,
            'provider' => $device->provider,
            'enabled' => $device->enabled,
            'app_version' => $device->app_version,
            'locale' => $device->locale,
            'last_seen_at' => $device->last_seen_at?->toISOString(),
        ];
    }
}
