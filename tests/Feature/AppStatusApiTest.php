<?php

namespace Tests\Feature;

use App\Models\AppAnnouncement;
use App\Models\AppVersionPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppStatusApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_status_returns_active_announcements_and_calculates_update_modes(): void
    {
        AppAnnouncement::query()->create([
            'presentation' => 'banner',
            'type' => 'info',
            'title' => 'Scheduled maintenance',
            'message' => 'Quotes may be delayed tonight.',
            'priority' => 5,
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addHour(),
        ]);
        AppAnnouncement::query()->create([
            'platform' => 'ios',
            'presentation' => 'modal',
            'type' => 'warning',
            'title' => 'iOS only',
            'message' => 'Not for Android.',
        ]);
        AppVersionPolicy::query()->create([
            'platform' => 'android',
            'latest_version' => '1.2.0',
            'latest_build' => 5,
            'minimum_version' => '1.1.0',
            'minimum_build' => 1,
            'store_url' => 'https://example.com/android',
        ]);

        $this->getJson('/api/pub/app-status?platform=android&version=1.0.9&build=9')
            ->assertOk()
            ->assertJsonPath('data.announcements.0.title', 'Scheduled maintenance')
            ->assertJsonCount(1, 'data.announcements')
            ->assertJsonPath('data.update.mode', 'required');

        $this->getJson('/api/pub/app-status?platform=android&version=1.1.1&build=1')
            ->assertOk()
            ->assertJsonPath('data.update.mode', 'available');

        $this->getJson('/api/pub/app-status?platform=android&version=1.2.0&build=5')
            ->assertOk()
            ->assertJsonPath('data.update.mode', 'none');
    }

    public function test_only_administrators_can_manage_announcements_and_version_policies(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/backoffice/announcements', $this->announcementPayload())
            ->assertForbidden();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/backoffice/announcements', $this->announcementPayload())
            ->assertCreated()
            ->assertJsonPath('data.presentation', 'modal');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/backoffice/version-policies', [
                'platform' => 'ios',
                'latest_version' => '2.0.0',
                'latest_build' => 8,
                'minimum_version' => '1.8.0',
                'minimum_build' => 1,
                'store_url' => 'https://example.com/ios',
            ])
            ->assertCreated()
            ->assertJsonPath('data.platform', 'ios');
    }

    public function test_backoffice_rejects_an_undismissable_announcement_without_a_cta(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/backoffice/announcements', [
                'presentation' => 'modal',
                'type' => 'critical',
                'title' => 'Action needed',
                'message' => 'This announcement must lead somewhere.',
                'is_dismissible' => false,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['action_label', 'action_url']);
    }

    /** @return array<string, mixed> */
    private function announcementPayload(): array
    {
        return [
            'platform' => 'android',
            'presentation' => 'modal',
            'type' => 'warning',
            'title' => 'New version',
            'message' => 'A better app experience is ready.',
            'action_label' => 'Open store',
            'action_url' => 'https://example.com/android',
            'is_dismissible' => true,
            'priority' => 10,
        ];
    }
}
