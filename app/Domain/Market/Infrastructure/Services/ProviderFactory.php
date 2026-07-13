<?php

namespace App\Domain\Market\Infrastructure\Services;

use App\Domain\Market\Infrastructure\Providers\Nobitex\NobitexClient;
use App\Domain\Market\Infrastructure\Providers\Nobitex\NobitexDriver;
use App\Domain\Market\Infrastructure\Providers\Nobitex\NobitexMapper;

class ProviderFactory
{
    public static function make(string $provider)
    {
        $provider = strtolower($provider);

        return match ($provider) {
            'nobitex' => new NobitexDriver(
                client: new NobitexClient(),
                mapper: new NobitexMapper()
            ),

            default => throw new \InvalidArgumentException("Unknown provider: {$provider}")
        };
    }
}
