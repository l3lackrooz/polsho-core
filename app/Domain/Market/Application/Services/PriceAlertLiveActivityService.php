<?php

namespace App\Domain\Market\Application\Services;

use App\Domain\Market\Infrastructure\Notifications\FcmPushNotificationSender;
use App\Domain\Market\Infrastructure\Persistence\Models\LiveActivityPushToken;

class PriceAlertLiveActivityService
{
    public function __construct(private readonly FcmPushNotificationSender $fcm) {}

    public function end(int $alertId): void
    {
        $now = now()->getTimestamp() * 1000;
        LiveActivityPushToken::query()->with('pushDevice')->where('price_alert_id', $alertId)->where('kind', 'activity_update')->where('enabled', true)->each(function (LiveActivityPushToken $token) use ($now): void {
            if ($token->pushDevice === null || ! $token->pushDevice->enabled) return;
            $result = $this->fcm->endLiveActivity($token->pushDevice, $token, ['current_price' => null, 'progress' => 1, 'away_fraction' => 0, 'phase' => 'expired', 'freshness' => 'stale', 'updated_at_ms' => $now, 'provider' => null, 'triggered_at_ms' => null]);
            if ($result->invalidTarget) $token->update(['enabled' => false, 'invalidated_at' => now()]);
        });
    }

    public function update(int $alertId, float $price, string $provider, int $timestamp): void
    {
        $alert = \App\Domain\Market\Infrastructure\Persistence\Models\PriceAlert::query()->find($alertId);
        if ($alert === null || $alert->status !== 'active') return;
        $start = $alert->baseline_price === null ? null : (float) $alert->baseline_price;
        $target = (float) $alert->target_price;
        $progress = $start === null || $target === $start ? null : max(0, min(1, ($price - $start) / ($target - $start)));
        $away = $target <= 0 ? null : abs($target - $price) / $target;
        $phase = $progress !== null && $progress >= .85 ? 'hot' : ($progress !== null && $progress >= .6 ? 'approaching' : ($away !== null && $away <= .01 ? 'hot' : 'active'));
        $state = ['current_price' => $price, 'progress' => $progress, 'away_fraction' => $away, 'phase' => $phase, 'freshness' => 'live', 'updated_at_ms' => $timestamp, 'provider' => $provider, 'triggered_at_ms' => null];
        LiveActivityPushToken::query()->with('pushDevice')->where('price_alert_id', $alertId)->where('kind', 'activity_update')->where('enabled', true)->each(function (LiveActivityPushToken $token) use ($state, $timestamp): void {
            if ($token->pushDevice === null || ! $token->pushDevice->enabled) return;
            $result = $this->fcm->updateLiveActivity($token->pushDevice, $token, $state, intdiv($timestamp, 1000));
            if ($result->invalidTarget) $token->update(['enabled' => false, 'invalidated_at' => now()]);
        });
    }
}
