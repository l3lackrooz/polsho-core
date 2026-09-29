<?php

namespace App\Domain\Market\Application\Milestones;

use App\Domain\Market\Infrastructure\Persistence\Models\ProviderMarket;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

class MilestoneEvaluator
{
    public function evaluate(array $aggregate): void
    {
        $rule = MilestoneRule::where('enabled', true)->whereHas('instrument', fn ($q) => $q
            ->where('symbol', $aggregate['instrument'])->where('status', 'active'))->first();
        if (! $rule) {
            return;
        }
        DB::transaction(function () use ($rule, $aggregate) {
            $rule = MilestoneRule::lockForUpdate()->find($rule->id);
            if (! $rule?->enabled || $rule->instrument->status !== 'active') {
                return;
            }
            $state = $rule->state ?? [];
            $groups = [];
            // These are the same filtered winners published to the application.
            // Neither an administrator nor a device chooses an exchange or price side.
            foreach (['best_buy' => 'best_ask', 'best_sell' => 'best_bid'] as $side => $key) {
                $track = $state[$side] ?? [];
                $match = $this->evaluateSide($rule, $aggregate, $aggregate[$key] ?? null, $side, $track);
                if ($track !== []) {
                    $state[$side] = $track;
                }
                if ($match !== null) {
                    $groups[$match['direction'].':'.$match['level']][] = $match;
                }
            }
            foreach ($groups as $matches) {
                $first = $matches[0];
                // One push when buy and sell confirm the same milestone together.
                // A later confirmation of the same milestone obeys the shared cooldown.
                $rule->events()->create([
                    'revision' => $rule->revision, 'symbol' => $rule->instrument->symbol,
                    'quote_unit' => $rule->instrument->quoteAsset->symbol,
                    'provider' => $first['provider'], 'provider_name' => $first['provider_name'],
                    'provider_market_id' => $first['provider_market_id'], 'price_source' => $first['price_source'],
                    'direction' => $first['direction'], 'level' => $first['level'], 'price' => $first['price'],
                    'quote_timestamp' => $first['timestamp'], 'matches' => $matches,
                    'expires_at' => now()->addMinutes(5), 'audience_max_user_id' => User::max('id') ?? 0,
                ]);
            }
            if ($state !== ($rule->state ?? [])) {
                $rule->update(['state' => $state]);
            }
        });
    }

    private function evaluateSide(MilestoneRule $rule, array $aggregate, ?array $quote, string $side, array &$state): ?array
    {
        if (! $quote || ($quote['is_reference'] ?? false)) {
            return null;
        }
        $market = ProviderMarket::with('provider')->whereKey($quote['provider_market_id'] ?? 0)
            ->where('instrument_id', $rule->instrument_id)->where('status', 'active')->first();
        if (! $market || $market->provider->status !== 'active' || ($market->provider->config['is_reference'] ?? false)) {
            return null;
        }
        $timestamp = (int) ($quote['timestamp'] ?? 0);
        $evaluationTimestamp = (int) ($aggregate['timestamp'] ?? $timestamp);
        $now = now()->getTimestampMs();
        $value = $quote[$side === 'best_buy' ? 'ask' : 'bid'] ?? null;
        if (! is_numeric($value) || ! is_finite((float) $value) || $value <= 0 || $value > 1e15
            || $timestamp > $now || $timestamp < $now - 60000 || $timestamp < $rule->configured_at
            || $evaluationTimestamp > $now || $evaluationTimestamp < $timestamp) {
            return null;
        }
        $price = BigDecimal::of((string) $value)->toScale(8, RoundingMode::HALF_UP);
        if (! $price->isPositive()) {
            return null;
        }
        $previousEvaluation = $state['evaluation_timestamp'] ?? $state['timestamp'] ?? 0;
        if ($evaluationTimestamp <= $previousEvaluation) {
            return null;
        }
        $fingerprint = $market->id.':'.$timestamp.':'.$price;
        $source = ['price' => (string) $price, 'timestamp' => $timestamp,
            'evaluation_timestamp' => $evaluationTimestamp, 'fingerprint' => $fingerprint,
            'provider' => $market->provider->slug, 'provider_name' => $market->provider->name,
            'provider_market_id' => $market->id, 'price_source' => $side];
        if (! isset($state['anchor']) || $evaluationTimestamp - $previousEvaluation > 60000
            || $timestamp - ($state['timestamp'] ?? 0) > 60000) {
            $state = [...$source, 'anchor' => (string) $price];

            return null;
        }
        if (($state['fingerprint'] ?? null) === $fingerprint) {
            $state['evaluation_timestamp'] = $evaluationTimestamp;

            return null;
        }
        $state = [...$state, ...$source];
        $step = BigDecimal::of($rule->step);
        $anchor = BigDecimal::of($state['anchor']);
        $up = $anchor->dividedBy($step, 0, RoundingMode::FLOOR)->plus(1)->multipliedBy($step);
        $down = $anchor->dividedBy($step, 0, RoundingMode::CEILING)->minus(1)->multipliedBy($step);
        $direction = $price->isGreaterThanOrEqualTo($up) ? 'up' : ($price->isLessThanOrEqualTo($down) ? 'down' : null);
        if ($direction === null) {
            unset($state['candidate']);

            return null;
        }
        $level = $price->dividedBy($step, 0, $direction === 'up' ? RoundingMode::FLOOR : RoundingMode::CEILING)->multipliedBy($step)->toScale(8);
        $candidate = $state['candidate'] ?? [];
        $continues = ($candidate['direction'] ?? null) === $direction && isset($candidate['level'])
            && ($direction === 'up' ? $price->isGreaterThanOrEqualTo($candidate['level']) : $price->isLessThanOrEqualTo($candidate['level']));
        $count = $continues ? ($candidate['count'] + 1) : 1;
        $state['candidate'] = ['level' => (string) $level, 'direction' => $direction, 'count' => $count];
        if ($count < $rule->confirmation_quotes) {
            return null;
        }
        $state['anchor'] = (string) $level;
        unset($state['candidate']);
        $recent = $rule->events()->where('direction', $direction)->where('level', (string) $level)
            ->where('created_at', '>', now()->subSeconds($rule->cooldown_seconds))->exists();
        if ($recent || ! in_array($rule->direction, ['both', $direction], true) || ! $level->isPositive()) {
            return null;
        }

        return [...$source, 'direction' => $direction, 'level' => (string) $level];
    }
}
