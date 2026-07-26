<?php

namespace App\Services;

use App\Domain\Market\Infrastructure\Persistence\Models\PriceAlert;
use App\Domain\Market\Infrastructure\Persistence\Models\PushDevice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteUserAccount
{
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            PriceAlert::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'paused',
                    'notify_push' => false,
                    'notify_in_app' => false,
                ]);

            PushDevice::query()
                ->where('user_id', $user->id)
                ->update([
                    'provider_token' => null,
                    'token_hash' => null,
                    'enabled' => false,
                    'invalidated_at' => now(),
                ]);

            $user->tokens()->delete();
            $user->delete();
        });
    }
}
