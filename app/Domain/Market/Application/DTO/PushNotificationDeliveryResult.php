<?php

namespace App\Domain\Market\Application\DTO;

class PushNotificationDeliveryResult
{
    private function __construct(
        public readonly string $status,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $error = null,
        public readonly bool $invalidTarget = false,
    ) {}

    public static function sent(?string $providerMessageId = null): self
    {
        return new self('sent', $providerMessageId);
    }

    public static function skipped(string $reason): self
    {
        return new self('skipped', error: $reason);
    }

    public static function failed(string $error, bool $invalidTarget = false): self
    {
        return new self('failed', error: $error, invalidTarget: $invalidTarget);
    }
}
