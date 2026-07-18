<?php

namespace App\Domain\Market\Infrastructure\Providers\Tala;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Application\DTO\QuoteDTO;

class TalaMapper
{
    /**
     * Configured remote symbols → the feed's actual row ids. The site's page
     * elements use short ids (geram18, bazartehran) while the /ajax/price
     * document prefixes them per group, so both spellings are accepted and
     * existing provider_markets rows keep working unchanged.
     */
    private const ALIASES = [
        'geram18' => 'gold_18k',
        'bazartehran' => 'gold_bazartehran',
        'mesghal' => 'gold_bazartehran',
    ];

    /**
     * The board carries a single reference price per row, so each one is
     * represented as a symmetric bid/ask quote rather than an order book.
     *
     * @param array<string, array<string, mixed>> $rows flattened /ajax/price rows
     * @param array<string, MarketSubscriptionDTO> $subscriptions keyed by remote symbol
     * @return array<int, QuoteDTO>
     */
    public function mapSnapshot(array $rows, array $subscriptions, string $provider): array
    {
        $quotes = [];

        foreach ($subscriptions as $remoteSymbol => $subscription) {
            $row = $this->resolveRow($rows, (string) $remoteSymbol);

            if ($row === null) {
                continue;
            }

            $price = $this->parsePrice($row['v'] ?? null);

            if ($price === null || $price <= 0.0) {
                continue;
            }

            $quotes[] = new QuoteDTO(
                instrument: $subscription->instrument,
                bid: $price,
                ask: $price,
                last: $price,
                provider: $provider,
                volume: null,
                timestamp: $this->parseTimestamp($row['m'] ?? null),
                providerMarketId: $subscription->providerMarketId,
            );
        }

        return $quotes;
    }

    /**
     * Exact feed id first, then the alias table, then the `gold_` group
     * prefix (so `ounce` or `24k` resolve without new aliases).
     *
     * @param array<string, array<string, mixed>> $rows
     * @return array<string, mixed>|null
     */
    private function resolveRow(array $rows, string $remoteSymbol): ?array
    {
        $candidates = [
            $remoteSymbol,
            self::ALIASES[$remoteSymbol] ?? null,
            'gold_'.$remoteSymbol,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== null && isset($rows[$candidate]) && is_array($rows[$candidate])) {
                return $rows[$candidate];
            }
        }

        return null;
    }

    private function parsePrice(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return null;
        }

        // The feed formats values with thousands separators, and some rows
        // arrive with Persian/Arabic numerals.
        $normalized = strtr(trim($value), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '٬' => '', '،' => '', ',' => '', ' ' => '', "\t" => '',
        ]);

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    /**
     * `m` is the board's update time as epoch seconds. Bad values fall back
     * to now so a malformed row can still surface (staleness is enforced by
     * the provider's max_quote_age config).
     */
    private function parseTimestamp(mixed $value): int
    {
        if (is_numeric($value) && (int) $value > 0) {
            return (int) $value * 1000;
        }

        return now()->getTimestampMs();
    }
}
