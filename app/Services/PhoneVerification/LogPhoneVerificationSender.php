<?php

namespace App\Services\PhoneVerification;

use Illuminate\Support\Facades\Log;
use LogicException;

class LogPhoneVerificationSender implements PhoneVerificationSender
{
    public function send(string $phone, string $code): void
    {
        if (app()->environment('production')) {
            throw new LogicException('The log phone verification driver cannot be used in production.');
        }

        Log::info('Phone verification code issued.', [
            'phone' => $phone,
            'code' => $code,
        ]);
    }
}
