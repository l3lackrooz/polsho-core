<?php

namespace App\Domain\Market\Application\Services;

use App\Domain\Market\Contracts\PushNotificationProvider;
use InvalidArgumentException;

class PushProviderRegistry
{
    /** @var array<string, PushNotificationProvider> */
    private array $providers = [];

    /** @param iterable<PushNotificationProvider> $providers */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->key()] = $provider;
        }
    }

    public function provider(string $key): PushNotificationProvider
    {
        return $this->providers[$key]
            ?? throw new InvalidArgumentException("Push provider [{$key}] is not registered.");
    }
}
