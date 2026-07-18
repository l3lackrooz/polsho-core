<?php

namespace Tests\Feature;

use App\Domain\Suggestion\Infrastructure\Persistence\Models\Suggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuggestionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_submit_an_instrument_suggestion(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/suggestions', [
            'type' => 'add_instrument',
            'subject' => 'SUI/USDT',
            'market_kind' => 'crypto',
            'exchange' => 'Binance',
            'note' => 'Would love this pair.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'add_instrument')
            ->assertJsonPath('data.subject', 'SUI/USDT')
            ->assertJsonPath('data.market_kind', 'crypto')
            ->assertJsonPath('data.status', 'under_review');

        $this->assertDatabaseHas('suggestions', [
            'user_id' => $user->id,
            'subject' => 'SUI/USDT',
            'status' => 'under_review',
        ]);
    }

    public function test_listing_an_instrument_on_an_exchange_requires_the_exchange(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/suggestions', ['type' => 'instrument_on_exchange', 'subject' => 'BTC/IRT'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('exchange');
    }

    public function test_an_exchange_request_drops_the_exchange_field_and_keeps_the_website(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/suggestions', [
            'type' => 'add_exchange',
            'subject' => 'Bit24',
            'website' => 'https://bit24.cash',
            'exchange' => 'should be ignored',
            'market_kind' => 'crypto',
        ])->assertCreated();

        $this->assertDatabaseHas('suggestions', [
            'subject' => 'Bit24',
            'website' => 'https://bit24.cash',
            'exchange' => null,
            'market_kind' => null,
        ]);
    }

    public function test_a_user_only_sees_their_own_suggestions(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        Suggestion::query()->create(['user_id' => $me->id, 'type' => 'add_exchange', 'subject' => 'Mine']);
        Suggestion::query()->create(['user_id' => $other->id, 'type' => 'add_exchange', 'subject' => 'Theirs']);

        $this->actingAs($me, 'sanctum')->getJson('/api/suggestions')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.subject', 'Mine');
    }

    public function test_backoffice_queue_is_admin_only(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user, 'sanctum')->getJson('/api/backoffice/suggestions')->assertForbidden();
    }

    public function test_an_admin_can_triage_and_delete_a_suggestion(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $suggestion = Suggestion::query()->create([
            'user_id' => User::factory()->create()->id,
            'type' => 'add_instrument',
            'subject' => 'TON/USDT',
        ]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/backoffice/suggestions')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.subject', 'TON/USDT')
            ->assertJsonPath('data.0.user.id', $suggestion->user_id);

        $this->actingAs($admin, 'sanctum')->putJson("/api/backoffice/suggestions/{$suggestion->id}", [
            'status' => 'planned',
            'admin_note' => 'Scheduled for next release.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'planned')
            ->assertJsonPath('data.admin_note', 'Scheduled for next release.');

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/backoffice/suggestions/{$suggestion->id}")
            ->assertOk();

        $this->assertDatabaseCount('suggestions', 0);
    }
}
