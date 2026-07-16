<?php

namespace Tests\Feature;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Asset\Models\Instrument;
use App\Domain\Market\Infrastructure\Persistence\Models\PriceAlert;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthenticationAndAlertEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_issues_a_token_and_sends_a_verification_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Polsho User',
            'email' => 'user@example.test',
            'phone' => '+989121234567',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
            'device_name' => 'iPhone',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'user@example.test')
            ->assertJsonPath('data.user.phone', '+989121234567')
            ->assertJsonPath('data.user.entitlements.active_alert_limit', 3)
            ->assertJsonPath('data.user.entitlements.is_email_verified', false)
            ->assertJsonPath('data.user.entitlements.is_phone_verified', false)
            ->assertJsonStructure(['data' => ['token']]);

        $user = User::query()->where('email', 'user@example.test')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_signed_email_verification_updates_the_identity_and_entitlements(): void
    {
        $user = User::factory()->unverified()->create();
        $user->phone = '+989121234567';
        $user->phone_verified_at = now();
        $user->save();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(10),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        );

        $this->getJson($verificationUrl)
            ->assertOk()
            ->assertJsonPath('data.user.entitlements.is_email_verified', true)
            ->assertJsonPath('data.user.entitlements.is_phone_verified', true)
            ->assertJsonPath('data.user.entitlements.active_alert_limit', 10);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_unverified_users_cannot_exceed_the_database_configured_alert_limit(): void
    {
        $user = User::factory()->create(['phone_verified_at' => null]);
        $instrument = $this->instrument();

        PriceAlert::query()->create([
            'user_id' => $user->id,
            'instrument_id' => $instrument->id,
            'scope' => 'best_market',
            'condition' => 'goes_above',
            'target_price' => 1,
        ]);
        PriceAlert::query()->create([
            'user_id' => $user->id,
            'instrument_id' => $instrument->id,
            'scope' => 'best_market',
            'condition' => 'goes_above',
            'target_price' => 2,
        ]);
        PriceAlert::query()->create([
            'user_id' => $user->id,
            'instrument_id' => $instrument->id,
            'scope' => 'best_market',
            'condition' => 'goes_above',
            'target_price' => 3,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/market/price-alerts', [
                'instrument_id' => $instrument->id,
                'scope' => 'best_market',
                'condition' => 'goes_above',
                'target_price' => 4,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('price_alerts');
    }

    private function instrument(): Instrument
    {
        $base = Asset::query()->create([
            'symbol' => 'USDT',
            'name' => 'Tether',
            'type' => 'crypto',
        ]);
        $quote = Asset::query()->create([
            'symbol' => 'IRT',
            'name' => 'Iranian Toman',
            'type' => 'fiat',
        ]);

        return Instrument::query()->create([
            'base_asset_id' => $base->id,
            'quote_asset_id' => $quote->id,
            'symbol' => 'USDT-IRT',
        ]);
    }
}
