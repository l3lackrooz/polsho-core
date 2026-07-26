<?php

namespace Tests\Feature;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Asset\Models\Instrument;
use App\Domain\Market\Infrastructure\Persistence\Models\PriceAlert;
use App\Domain\Market\Infrastructure\Persistence\Models\PushDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_soft_delete_their_account_and_all_activity_is_disabled(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('polsho');
        $instrument = $this->instrument();
        $alert = PriceAlert::query()->create([
            'user_id' => $user->id,
            'instrument_id' => $instrument->id,
            'scope' => 'best_market',
            'condition' => 'goes_above',
            'target_price' => 100,
            'status' => 'active',
            'notify_push' => true,
            'notify_in_app' => true,
        ]);
        $device = PushDevice::query()->create([
            'user_id' => $user->id,
            'installation_id' => '0190c7d0-6f0a-79cd-b4c0-4b7e54e8bb39',
            'platform' => 'android',
            'provider' => 'pushe',
            'provider_token' => 'provider-token',
            'token_hash' => hash('sha256', 'provider-token'),
            'enabled' => true,
            'last_seen_at' => now(),
        ]);

        $this->withToken($token->plainTextToken)
            ->deleteJson('/api/auth/account')
            ->assertNoContent();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);

        $alert->refresh();
        $this->assertSame('paused', $alert->status);
        $this->assertFalse($alert->notify_push);
        $this->assertFalse($alert->notify_in_app);

        $device->refresh();
        $this->assertFalse($device->enabled);
        $this->assertNull($device->provider_token);
        $this->assertNull($device->token_hash);
        $this->assertNotNull($device->invalidated_at);

        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'polsho',
        ])->assertUnauthorized();
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
