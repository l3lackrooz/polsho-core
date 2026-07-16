<?php

namespace App\Services;

use App\Models\User;

class AuthUserPresenter
{
    public function __construct(private readonly AlertEntitlementService $entitlements) {}

    /** @return array<string, mixed> */
    public function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => $user->is_admin,
            'phone' => $user->phone,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'phone_verified_at' => $user->phone_verified_at?->toISOString(),
            'entitlements' => $this->entitlements->forUser($user),
        ];
    }
}
