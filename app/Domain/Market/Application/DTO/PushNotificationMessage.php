<?php

namespace App\Domain\Market\Application\DTO;

class PushNotificationMessage
{
    /**
     * @param array<string, mixed> $data
     * @param array{attributes: array<string, mixed>, content_state: array<string, mixed>, timestamp: int}|null $liveActivityStart
     */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly array $data,
        public readonly string $deepLink,
        public readonly ?array $liveActivityStart = null,
    ) {}

    /** @return array<string, string> */
    public function stringData(): array
    {
        return array_map(
            static fn (mixed $value): string => match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                is_scalar($value) => (string) $value,
                default => json_encode($value, JSON_THROW_ON_ERROR),
            },
            $this->data,
        );
    }
}
