<?php

namespace App\Services\PhoneVerification;

use Illuminate\Support\Facades\Http;
use LogicException;

class HttpPhoneVerificationSender implements PhoneVerificationSender
{
    public function send(string $phone, string $code): void
    {
        $url = (string) config('phone_verification.http.url');
        if ($url === '') {
            throw new LogicException('PHONE_VERIFICATION_HTTP_URL is required for the HTTP driver.');
        }
        if (
            app()->environment('production')
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https'
        ) {
            throw new LogicException('The production phone verification endpoint must use HTTPS.');
        }

        $token = (string) config('phone_verification.http.token');
        $request = Http::asJson()
            ->acceptJson()
            ->timeout((int) config('phone_verification.http.timeout_seconds'))
            ->retry(2, 250);

        if ($token !== '') {
            $request = $request->withToken($token);
        }

        $request->post($url, [
            'phone' => $phone,
            'code' => $code,
            'template' => (string) config('phone_verification.http.template'),
        ])->throw();
    }
}
