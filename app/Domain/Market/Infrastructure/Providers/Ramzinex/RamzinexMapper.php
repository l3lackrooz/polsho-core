<?php

namespace App\Domain\Market\Infrastructure\Providers\Ramzinex;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Application\DTO\QuoteDTO;
use App\Domain\Market\Infrastructure\Support\Utility\ProviderQuoteFactory;

class RamzinexMapper
{
    public function __construct(
        private readonly ProviderQuoteFactory $quotes = new ProviderQuoteFactory,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, MarketSubscriptionDTO>  $subscriptions
     * @return array<int, QuoteDTO>
     */
    public function mapSnapshot(array $rows, array $subscriptions, string $provider): array
    {
        $quotes = [];
        foreach ($rows as $row) {

            $remoteSymbol = strtolower(
                (string) ($row['tv_symbol']['ramzinex'] ?? '')
            );
            if ($remoteSymbol === '' || ! isset($subscriptions[$remoteSymbol])) {
                continue;
            }

            $financial = $row['financial']['last24h'] ?? [];

            $quotes[] = $this->quotes->make(
                subscription: $subscriptions[$remoteSymbol],
                bid: isset($row['buy']) ? (float) $row['buy'] : 0.0,
                ask: isset($row['sell']) ? (float) $row['sell'] : 0.0,
                last: isset($financial['close']) ? (float) $financial['close'] : null,
                provider: $provider,
                volume: isset($financial['base_volume']) ? (float) $financial['base_volume'] : null,
                timestamp: (int) round(microtime(true) * 1000),
            );
        }

        return $quotes;
    }
}
