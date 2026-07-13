<?php

namespace App\Domain\Market\Infrastructure\Notifications;

use App\Domain\Market\Application\DTO\AggregatedQuoteDTO;
use App\Domain\Market\Application\DTO\QuoteDTO;
use Illuminate\Support\Carbon;

class BalePriceAlertFormatter
{
    public function format(
        AggregatedQuoteDTO $aggregated,
        array $providerChanges24h = [],
    ): string {
        $referencePrice = $this->resolveReferencePrice($aggregated);
        $summaryChange = $this->resolveSummaryChange($providerChanges24h);

        return implode("\n\n", [
            sprintf('%s %s', $this->resolveEmoji($summaryChange), $this->formatInstrument($aggregated->instrument)),
            implode("\n", array_filter([
                sprintf('🕒 %s', $this->formatTime($aggregated->timestamp)),
                $referencePrice !== null
                    ? sprintf('📌 مرجع: %s', $this->formatPriceLine($aggregated->instrument, $referencePrice))
                    : null,
                ...$this->formatProviderLines($aggregated, $referencePrice),
                $this->formatBestBuyLine($aggregated),
                $this->formatBestSellLine($aggregated),
                sprintf('📦 حجم: %s', $this->formatVolume($this->resolveVolume($aggregated))),
                $this->formatChangeSummaryLine($providerChanges24h),
            ])),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function formatProviderLines(AggregatedQuoteDTO $aggregated, ?float $referencePrice): array
    {
        $providers = $aggregated->providers;

        usort($providers, function (QuoteDTO $left, QuoteDTO $right): int {
            return ($this->resolveQuotePrice($right) <=> $this->resolveQuotePrice($left))
                ?: strcmp($left->provider, $right->provider);
        });

        return array_map(function (QuoteDTO $quote) use ($referencePrice): string {
            $price = $this->resolveQuotePrice($quote);
            $deviation = $this->formatDeviation($price, $referencePrice);

            return trim(sprintf(
                '%s: %s%s',
                $this->formatProviderName($quote->provider),
                $this->formatPriceLine($quote->instrument, $price),
                $deviation === null ? '' : sprintf(' (%s)', $deviation),
            ));
        }, $providers);
    }

    private function formatInstrument(string $instrument): string
    {
        return str_replace('-', '/', strtoupper($instrument));
    }

    private function formatPriceLine(string $instrument, float $price): string
    {
        [, $quote] = array_pad(explode('-', strtoupper($instrument), 2), 2, '');

        return trim(sprintf(
            '%s %s',
            $this->formatPriceByQuote($price, $quote),
            $quote,
        ));
    }

    private function formatPriceByQuote(float $price, string $quote): string
    {
        $decimals = in_array($quote, ['IRR', 'IRT'], true) ? 0 : 2;

        return number_format($price, $decimals, '.', ',');
    }

    private function formatPercent(?float $change24h): string
    {
        if ($change24h === null) {
            return 'نامشخص';
        }

        return sprintf('%+.2f%%', $change24h);
    }

    private function formatVolume(?float $volume24h): string
    {
        if ($volume24h === null) {
            return 'نامشخص';
        }

        $abs = abs($volume24h);

        if ($abs >= 1_000_000_000) {
            return sprintf('%.1fB', $volume24h / 1_000_000_000);
        }

        if ($abs >= 1_000_000) {
            return sprintf('%.1fM', $volume24h / 1_000_000);
        }

        if ($abs >= 1_000) {
            return sprintf('%.1fK', $volume24h / 1_000);
        }

        return number_format($volume24h, 2, '.', ',');
    }

    private function formatTime(int $timestamp): string
    {
        if ($timestamp > 9999999999) {
            $timestamp = (int) floor($timestamp / 1000);
        }

        return Carbon::createFromTimestamp($timestamp)
            ->setTimezone('Asia/Tehran')
            ->format('H:i:s');
    }

    private function resolveEmoji(?float $change24h): string
    {
        if ($change24h === null) {
            return '📢';
        }

        return match (true) {
            $change24h > 0 => '🚀',
            $change24h < 0 => '🔻',
            default => '➖',
        };
    }

    private function resolveQuotePrice(QuoteDTO $quote): float
    {
        if ($quote->last !== null) {
            return $quote->last;
        }

        $mid = $quote->mid();

        if ($mid !== null) {
            return $mid;
        }

        if ($quote->ask > 0.0) {
            return $quote->ask;
        }

        return $quote->bid;
    }

    private function resolveReferencePrice(AggregatedQuoteDTO $aggregated): ?float
    {
        $prices = array_values(array_filter(array_map(
            fn (QuoteDTO $quote): float => $this->resolveQuotePrice($quote),
            $aggregated->providers,
        ), static fn (float $price): bool => $price > 0.0));

        if ($prices === []) {
            return null;
        }

        sort($prices);
        $count = count($prices);
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return $prices[$middle];
        }

        return ($prices[$middle - 1] + $prices[$middle]) / 2;
    }

    private function formatDeviation(float $price, ?float $referencePrice): ?string
    {
        if ($referencePrice === null || $referencePrice <= 0.0) {
            return null;
        }

        $deviation = (($price - $referencePrice) / $referencePrice) * 100;

        if (abs($deviation) < 0.005) {
            return '±0.00%';
        }

        return sprintf('%+.2f%%', $deviation);
    }

    private function formatProviderName(string $provider): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', strtolower($provider))));
    }

    private function formatBestBuyLine(AggregatedQuoteDTO $aggregated): ?string
    {
        if ($aggregated->bestAsk === null) {
            return null;
        }

        return sprintf(
            '🟢 بهترین خرید: %s - %s',
            $this->formatProviderName($aggregated->bestAsk->provider),
            $this->formatPriceLine($aggregated->bestAsk->instrument, $aggregated->bestAsk->ask),
        );
    }

    private function formatBestSellLine(AggregatedQuoteDTO $aggregated): ?string
    {
        if ($aggregated->bestBid === null) {
            return null;
        }

        return sprintf(
            '🔴 بهترین فروش: %s - %s',
            $this->formatProviderName($aggregated->bestBid->provider),
            $this->formatPriceLine($aggregated->bestBid->instrument, $aggregated->bestBid->bid),
        );
    }

    private function resolveVolume(AggregatedQuoteDTO $aggregated): ?float
    {
        $volumes = array_filter(
            array_map(static fn (QuoteDTO $quote): ?float => $quote->volume, $aggregated->providers),
            static fn (?float $volume): bool => $volume !== null,
        );

        if ($volumes === []) {
            return null;
        }

        return max($volumes);
    }

    /**
     * @param array<string, float|null> $providerChanges24h
     */
    private function formatChangeSummaryLine(array $providerChanges24h): ?string
    {
        $parts = [];

        foreach ($providerChanges24h as $provider => $change) {
            if ($change === null) {
                continue;
            }

            $parts[] = sprintf('%s %s', $this->formatProviderName($provider), $this->formatPercent($change));
        }

        if ($parts === []) {
            return null;
        }

        return sprintf('📊 تغییر ۲۴ساعت: %s', implode(' | ', $parts));
    }

    /**
     * @param array<string, float|null> $providerChanges24h
     */
    private function resolveSummaryChange(array $providerChanges24h): ?float
    {
        $changes = array_values(array_filter($providerChanges24h, static fn (?float $change): bool => $change !== null));

        if ($changes === []) {
            return null;
        }

        return array_sum($changes) / count($changes);
    }
}
