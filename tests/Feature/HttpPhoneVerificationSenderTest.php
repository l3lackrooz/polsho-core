<?php

namespace Tests\Feature;

use App\Services\PhoneVerification\HttpPhoneVerificationSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HttpPhoneVerificationSenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_the_code_to_the_configured_sms_gateway(): void
    {
        config()->set([
            'phone_verification.http.url' => 'https://sms.example.test/verification',
            'phone_verification.http.token' => 'gateway-token',
            'phone_verification.http.template' => 'polsho-otp',
            'phone_verification.http.timeout_seconds' => 5,
        ]);
        Http::fake([
            'https://sms.example.test/verification' => Http::response(['accepted' => true]),
        ]);

        (new HttpPhoneVerificationSender)->send('+989121234567', '123456');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://sms.example.test/verification'
            && $request->hasHeader('Authorization', 'Bearer gateway-token')
            && $request['phone'] === '+989121234567'
            && $request['code'] === '123456'
            && $request['template'] === 'polsho-otp');
    }
}
