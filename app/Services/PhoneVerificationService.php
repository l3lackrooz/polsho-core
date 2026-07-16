<?php

namespace App\Services;

use App\Models\PhoneVerificationCode;
use App\Models\User;
use App\Services\PhoneVerification\PhoneVerificationSender;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PhoneVerificationService
{
    public function __construct(private readonly PhoneVerificationSender $sender) {}

    public function updatePhone(User $user, string $phone): User
    {
        if ($user->phone === $phone) {
            return $user;
        }

        return DB::transaction(function () use ($user, $phone): User {
            PhoneVerificationCode::query()->where('user_id', $user->id)->delete();
            $user->forceFill([
                'phone' => $phone,
                'phone_verified_at' => null,
            ])->save();

            return $user->refresh();
        });
    }

    public function sendCode(User $user): void
    {
        if ($user->phone === null) {
            throw ValidationException::withMessages([
                'phone' => 'Add a phone number before requesting a verification code.',
            ]);
        }

        $code = (string) random_int(100000, 999999);
        $record = DB::transaction(function () use ($user, $code): PhoneVerificationCode {
            $lastCode = PhoneVerificationCode::query()
                ->where('user_id', $user->id)
                ->whereNull('consumed_at')
                ->latest('sent_at')
                ->lockForUpdate()
                ->first();
            $cooldown = now()->subSeconds((int) config('phone_verification.resend_cooldown_seconds'));

            if ($lastCode !== null && $lastCode->sent_at->greaterThan($cooldown)) {
                throw ValidationException::withMessages([
                    'phone' => 'Please wait before requesting another verification code.',
                ]);
            }

            PhoneVerificationCode::query()
                ->where('user_id', $user->id)
                ->whereNull('consumed_at')
                ->delete();

            return PhoneVerificationCode::query()->create([
                'user_id' => $user->id,
                'phone' => $user->phone,
                'code_hash' => Hash::make($code),
                'sent_at' => now(),
                'expires_at' => now()->addSeconds((int) config('phone_verification.code_ttl_seconds')),
            ]);
        });

        try {
            $this->sender->send($user->phone, $code);
        } catch (\Throwable $exception) {
            $record->delete();

            throw $exception;
        }
    }

    public function verify(User $user, string $code): User
    {
        return DB::transaction(function () use ($user, $code): User {
            $record = PhoneVerificationCode::query()
                ->where('user_id', $user->id)
                ->where('phone', $user->phone)
                ->whereNull('consumed_at')
                ->latest('sent_at')
                ->lockForUpdate()
                ->first();

            if ($record === null || $record->expires_at->isPast()) {
                throw $this->invalidCode();
            }

            $maximumAttempts = (int) config('phone_verification.max_attempts');
            if ($record->attempts >= $maximumAttempts) {
                $record->forceFill(['consumed_at' => now()])->save();

                throw $this->invalidCode();
            }

            $record->increment('attempts');

            if (! Hash::check($code, $record->code_hash)) {
                throw $this->invalidCode();
            }

            $record->forceFill(['consumed_at' => now()])->save();
            $user->forceFill(['phone_verified_at' => now()])->save();

            return $user->refresh();
        });
    }

    private function invalidCode(): ValidationException
    {
        return ValidationException::withMessages([
            'code' => 'The verification code is invalid or expired.',
        ]);
    }
}
