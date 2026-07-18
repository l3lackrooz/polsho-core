<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

class RealtimeAlertBroadcastAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanctum_user_can_authorize_only_their_private_alert_channel(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        app(BroadcastManager::class)->forgetDrivers();
        Broadcast::channel('App.Models.User.{id}', function ($joinedUser, $id) {
            return (int) $joinedUser->id === (int) $id;
        }, ['guards' => ['sanctum']]);
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $userToken = $user->createToken('realtime-test')->plainTextToken;
        $otherUserToken = $otherUser->createToken('realtime-test')->plainTextToken;

        $this->withToken($userToken)
            ->postJson('/api/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-App.Models.User.'.$user->id,
            ])
            ->assertOk()
            ->assertJsonStructure(['auth']);

        Auth::forgetGuards();
        $this->flushHeaders()
            ->withToken($otherUserToken)
            ->postJson('/api/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-App.Models.User.'.$user->id,
            ])
            ->assertForbidden();
    }
}
