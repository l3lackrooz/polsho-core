<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackofficeUserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrators_can_list_and_filter_safe_user_data(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create([
            'name' => 'Verified customer',
            'email' => 'verified@example.com',
            'phone' => '+989121234567',
            'email_verified_at' => now(),
        ]);
        User::factory()->create(['name' => 'Other customer']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/backoffice/users?search=verified&verification=verified&per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.email', 'verified@example.com')
            ->assertJsonPath('data.0.phone', '+989121234567')
            ->assertJsonPath('data.0.email_verified_at', fn ($value): bool => $value !== null)
            ->assertJsonMissingPath('data.0.password');
    }

    public function test_non_administrators_cannot_list_users(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/backoffice/users')
            ->assertForbidden();
    }
}
