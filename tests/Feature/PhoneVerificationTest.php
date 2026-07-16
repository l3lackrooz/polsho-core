<?php

namespace Tests\Feature;

use App\Models\PhoneVerificationCode;
use App\Models\User;
use App\Services\PhoneVerification\PhoneVerificationSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PhoneVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_verification_uses_a_hashed_code_and_upgrades_entitlements(): void
    {
        $code = null;
        $sender = Mockery::mock(PhoneVerificationSender::class);
        $sender->shouldReceive('send')
            ->once()
            ->withArgs(function (string $phone, string $sentCode) use (&$code): bool {
                $code = $sentCode;

                return $phone === '+989121234567';
            });
        $this->app->instance(PhoneVerificationSender::class, $sender);
        $user = User::factory()->create(['phone' => '+989121234567']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/phone/verification-code')
            ->assertAccepted()
            ->assertJsonPath('success', true);

        $record = PhoneVerificationCode::query()->sole();
        $this->assertNotSame($code, $record->code_hash);
        $this->assertSame(0, $record->attempts);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/phone/verify', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('data.user.entitlements.is_phone_verified', true)
            ->assertJsonPath('data.user.entitlements.active_alert_limit', 10);

        $this->assertNotNull($user->fresh()->phone_verified_at);
        $this->assertNotNull($record->fresh()->consumed_at);
    }

    public function test_phone_code_resend_is_limited_by_the_cooldown(): void
    {
        $sender = Mockery::mock(PhoneVerificationSender::class);
        $sender->shouldReceive('send')->once();
        $this->app->instance(PhoneVerificationSender::class, $sender);
        $user = User::factory()->create(['phone' => '+989121234567']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/phone/verification-code')
            ->assertAccepted();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/phone/verification-code')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_changing_the_phone_invalidates_existing_verification(): void
    {
        $user = User::factory()->create([
            'phone' => '+989121234567',
            'phone_verified_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/auth/phone', ['phone' => '+989123456789'])
            ->assertOk()
            ->assertJsonPath('data.user.entitlements.is_phone_verified', false);

        $user->refresh();
        $this->assertSame('+989123456789', $user->phone);
        $this->assertNull($user->phone_verified_at);
    }
}
