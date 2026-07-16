<?php

namespace App\Services;

use App\Domain\Market\Infrastructure\Persistence\Models\PriceAlert;
use App\Models\ApplicationSetting;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AlertEntitlementService
{
    private const UNVERIFIED_LIMIT_KEY = 'alerts.unverified_active_limit';

    private const VERIFIED_LIMIT_KEY = 'alerts.verified_active_limit';

    /** @return array<string, bool|int> */
    public function forUser(User $user): array
    {
        $activeAlertCount = $this->activeAlertCount($user->id);
        $activeAlertLimit = $this->activeAlertLimit($user);

        return [
            'is_email_verified' => $user->hasVerifiedEmail(),
            'is_phone_verified' => $user->phone_verified_at !== null,
            'active_alert_count' => $activeAlertCount,
            'active_alert_limit' => $activeAlertLimit,
            'can_create_alert' => $activeAlertCount < $activeAlertLimit,
        ];
    }

    public function assertCanCreate(User $user): void
    {
        $activeAlertCount = $this->activeAlertCount($user->id);
        $activeAlertLimit = $this->activeAlertLimit($user);

        if ($activeAlertCount >= $activeAlertLimit) {
            throw ValidationException::withMessages([
                'price_alerts' => "Your current verification level allows up to {$activeAlertLimit} active alerts.",
            ]);
        }
    }

    private function activeAlertCount(int $userId): int
    {
        return PriceAlert::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['active', 'paused'])
            ->count();
    }

    private function activeAlertLimit(User $user): int
    {
        $key = $user->hasVerifiedEmail() && $user->phone_verified_at !== null
            ? self::VERIFIED_LIMIT_KEY
            : self::UNVERIFIED_LIMIT_KEY;
        $fallback = $key === self::VERIFIED_LIMIT_KEY ? 10 : 3;
        $value = ApplicationSetting::query()->where('key', $key)->value('value');

        return is_numeric($value) && (int) $value >= 0 ? (int) $value : $fallback;
    }
}
