<?php

namespace App\Services\PhoneVerification;

interface PhoneVerificationSender
{
    public function send(string $phone, string $code): void;
}
