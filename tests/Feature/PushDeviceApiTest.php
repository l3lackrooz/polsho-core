<?php

namespace Tests\Feature;

use App\Domain\Market\Infrastructure\Persistence\Models\PushDevice;
use App\Domain\Market\Infrastructure\Persistence\Models\LiveActivityPushToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PushDeviceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authentication_is_required_to_register_a_push_device(): void
    {
        $installationId = (string) Str::uuid();

        $this->putJson("/api/push/devices/{$installationId}", [
            'platform' => 'android',
            'provider' => 'pushe',
        ])->assertUnauthorized();
    }

    public function test_an_ios_fcm_installation_can_be_registered_and_refreshed(): void
    {
        $user = User::factory()->create();
        $installationId = (string) Str::uuid();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/push/devices/{$installationId}", [
                'platform' => 'ios',
                'provider' => 'fcm',
                'provider_token' => 'first-fcm-token',
                'app_version' => '1.0.0+1',
                'locale' => 'fa-IR',
            ])
            ->assertCreated()
            ->assertJsonPath('data.installation_id', $installationId)
            ->assertJsonPath('data.platform', 'ios')
            ->assertJsonPath('data.provider', 'fcm')
            ->assertJsonPath('data.enabled', true)
            ->assertJsonMissingPath('data.provider_token');

        $device = PushDevice::query()->sole();
        $this->assertSame('first-fcm-token', $device->provider_token);
        $this->assertSame(hash('sha256', 'first-fcm-token'), $device->token_hash);
        $this->assertNotSame(
            'first-fcm-token',
            DB::table('push_devices')->whereKey($device->id)->value('provider_token'),
        );

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/push/devices/{$installationId}", [
                'platform' => 'ios',
                'provider' => 'fcm',
                'provider_token' => 'rotated-fcm-token',
            ])
            ->assertOk();

        $this->assertDatabaseCount('push_devices', 1);
        $this->assertSame('rotated-fcm-token', $device->refresh()->provider_token);
        $this->assertSame(hash('sha256', 'rotated-fcm-token'), $device->token_hash);
    }

    public function test_platform_provider_contract_is_enforced(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/push/devices/'.Str::uuid(), [
                'platform' => 'android',
                'provider' => 'fcm',
                'provider_token' => 'token',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('provider');

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/push/devices/'.Str::uuid(), [
                'platform' => 'ios',
                'provider' => 'fcm',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('provider_token');

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/push/devices/'.Str::uuid(), [
                'platform' => 'android',
                'provider' => 'pushe',
                'provider_token' => 'must-not-be-sent',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('provider_token');
    }

    public function test_an_ios_live_activity_token_can_be_registered_for_an_installation(): void
    {
        $user = User::factory()->create();
        $installationId = (string) Str::uuid();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/push/devices/{$installationId}", [
                'platform' => 'ios',
                'provider' => 'fcm',
                'provider_token' => 'fcm-token',
            ])
            ->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/push/devices/{$installationId}/live-activity-tokens", [
                'kind' => 'push_to_start',
                'token' => 'activity-start-token',
            ])
            ->assertOk()
            ->assertJsonPath('data.kind', 'push_to_start')
            ->assertJsonPath('data.enabled', true)
            ->assertJsonMissingPath('data.token');

        $token = LiveActivityPushToken::query()->sole();
        $this->assertSame('activity-start-token', $token->token);
        $this->assertSame(hash('sha256', 'activity-start-token'), $token->token_hash);
        $this->assertNotSame(
            'activity-start-token',
            DB::table('live_activity_push_tokens')->whereKey($token->id)->value('token'),
        );

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/push/devices/{$installationId}/live-activity-tokens", [
                'kind' => 'activity_update',
                'activity_id' => 'activity-123',
                'token' => 'activity-update-token',
            ])
            ->assertOk()
            ->assertJsonPath('data.activity_id', 'activity-123');

        $this->assertDatabaseCount('live_activity_push_tokens', 2);
    }

    public function test_an_installation_moves_to_the_current_user_and_can_be_disabled(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $installationId = (string) Str::uuid();

        $this->actingAs($firstUser, 'sanctum')
            ->putJson("/api/push/devices/{$installationId}", [
                'platform' => 'android',
                'provider' => 'pushe',
            ])
            ->assertCreated();

        $this->actingAs($secondUser, 'sanctum')
            ->putJson("/api/push/devices/{$installationId}", [
                'platform' => 'android',
                'provider' => 'pushe',
            ])
            ->assertOk();

        $device = PushDevice::query()->sole();
        $this->assertSame($secondUser->id, $device->user_id);

        $this->actingAs($firstUser, 'sanctum')
            ->deleteJson("/api/push/devices/{$installationId}")
            ->assertNoContent();
        $this->assertTrue($device->refresh()->enabled);

        $this->actingAs($secondUser, 'sanctum')
            ->deleteJson("/api/push/devices/{$installationId}")
            ->assertNoContent();

        $device->refresh();
        $this->assertFalse($device->enabled);
        $this->assertNotNull($device->invalidated_at);
    }
}
