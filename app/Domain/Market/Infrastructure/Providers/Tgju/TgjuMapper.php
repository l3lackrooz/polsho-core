<?php

namespace App\Domain\Market\Infrastructure\Providers\Tgju;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Application\DTO\QuoteDTO;
use Carbon\Carbon;
use Throwable;

class TgjuMapper
{
    /**
     * TGJU semantics: reference rates, not an order book. Each row carries a
     * single price `p` (plus `h`/`l` day extremes), so bid and ask are both
     * set to that price. Prices arrive as strings with thousands separators
     * ("1,781,000") and occasionally stray whitespace.
     *
     * @param array<string, array<string, mixed>> $rows keyed by remote symbol
     * @param array<string, MarketSubscriptionDTO> $subscriptions keyed by remote symbol
     * @return array<int, QuoteDTO>
     */
    public function mapSnapshot(array $rows, array $subscriptions, string $provider): array
    {
        $quotes = [];

        foreach ($rows as $symbol => $row) {
            $symbol = (string) $symbol;

            if ($symbol === '' || !isset($subscriptions[$symbol]) || !is_array($row)) {
                continue;
            }

            $price = $this->parsePrice($row['p'] ?? null);

            if ($price === null || $price <= 0) {
                continue;
            }

            $quotes[] = new QuoteDTO(
                instrument: $subscriptions[$symbol]->instrument,
                bid: $price,
                ask: $price,
                last: $price,
                provider: $provider,
                volume: null,
                timestamp: $this->parseTimestamp($row['ts'] ?? null),
                providerMarketId: $subscriptions[$symbol]->providerMarketId,
            );
        }

        return $quotes;
    }

    private function parsePrice(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $normalized = str_replace([',', ' ', "\t"], '', trim($value));

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    /**
     * TGJU datetimes ("2026-07-11 18:27:21") are Tehran local time.
     * Falls back to now when the field is missing or malformed.
     */
    private function parseTimestamp(mixed $value): int
    {
        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value, 'Asia/Tehran')->getTimestampMs();
            } catch (Throwable) {
                // Fall through to the current time.
            }
        }

        return (int) round(microtime(true) * 1000);
    }
}
