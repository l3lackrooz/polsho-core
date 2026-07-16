<?php

namespace App\Domain\Market\Application\DTO;

class PushNotificationTarget
{
    public function __construct(
        public readonly string $provider,
        public readonly string $platform,
        public readonly string $address,
        public readonly ?int $pushDeviceId = null,
    ) {}

    public function hash(): string
    {
        return hash('sha256', $this->address);
    }
}
