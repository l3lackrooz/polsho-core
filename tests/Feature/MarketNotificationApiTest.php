<?php

namespace Tests\Feature;

use App\Domain\Market\Infrastructure\Notifications\PriceAlertTriggeredNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketNotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_list_only_their_notifications_with_unread_count(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $notification = $this->notificationFor($user, read: false);
        $this->notificationFor($otherUser, read: false);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/market/notifications');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonPath('data.0.id', $notification->id)
            ->assertJsonPath('data.0.type', 'price_alert.triggered')
            ->assertJsonPath('data.0.price_alert_id', '42')
            ->assertJsonPath('data.0.instrument', 'USDT-IRT')
            ->assertJsonPath('data.0.read_at', null);
    }

    public function test_users_can_mark_their_notification_or_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        $first = $this->notificationFor($user, read: false);
        $second = $this->notificationFor($user, read: false);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/market/notifications/{$first->id}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $first->id)
            ->assertJsonPath('data.read_at', fn (?string $readAt): bool => $readAt !== null);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/market/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.updated', 1);

        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNotNull($second->fresh()->read_at);
    }

    private function notificationFor(User $user, bool $read): object
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => PriceAlertTriggeredNotification::class,
            'data' => [
                'type' => 'price_alert.triggered',
                'price_alert_id' => 42,
                'instrument' => 'USDT-IRT',
                'price' => 92100,
                'target_price' => 92000,
                'provider' => 'nobitex',
                'title' => 'Price alert triggered',
                'body' => 'USDT-IRT reached 92,100.',
            ],
            'read_at' => $read ? now() : null,
        ]);
    }
}
