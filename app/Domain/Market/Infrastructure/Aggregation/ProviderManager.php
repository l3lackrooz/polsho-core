<?php

namespace App\Domain\Market\Infrastructure\Aggregation;

use App\Domain\Market\Contracts\Capabilities\SupportsPriceSnapshot;
use App\Domain\Market\Contracts\Capabilities\SupportsPriceStream;
use App\Domain\Market\Contracts\MarketDataProviderInterface;
use RuntimeException;

class ProviderManager
{
    /**
     * @param array<int, MarketDataProviderInterface> $providers
     */
    public function __construct(
        private array $providers,
    ) {}

    /**
     * @return array<int, MarketDataProviderInterface>
     */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * @return array<int, MarketDataProviderInterface&SupportsPriceSnapshot>
     */
    public function snapshotProviders(): array
    {
        return array_values(array_filter(
            $this->providers,
            static fn (MarketDataProviderInterface $provider): bool => $provider instanceof SupportsPriceSnapshot,
        ));
    }

    public function stream(string $provider): MarketDataProviderInterface&SupportsPriceStream
    {
        $expected = strtolower(trim($provider));

        foreach ($this->providers as $candidate) {
            if (strtolower($candidate->name()) === $expected && $candidate instanceof SupportsPriceStream) {
                return $candidate;
            }
        }

        throw new RuntimeException("Streaming provider [{$provider}] is not registered.");
    }
}
