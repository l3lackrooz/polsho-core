<?php

namespace Tests\Feature;

use App\Domain\Market\Application\DTO\PushNotificationDeliveryResult;
use App\Domain\Market\Application\DTO\PushNotificationMessage;
use App\Domain\Market\Application\DTO\PushNotificationTarget;
use App\Domain\Market\Application\Jobs\SendAppAnnouncementPushJob;
use App\Domain\Market\Application\Services\PushNotificationTargetResolver;
use App\Domain\Market\Application\Services\PushProviderRegistry;
use App\Domain\Market\Contracts\PushNotificationProvider;
use App\Models\AppAnnouncement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PushLanguageTest extends TestCase
{
    use RefreshDatabase;

    private function device(User $user, string $platform, ?string $locale, int $age = 0): void
    {
        $token = Str::uuid()->toString();
        $user->pushDevices()->create([
            'installation_id' => Str::uuid()->toString(), 'platform' => $platform,
            'provider' => $platform === 'ios' ? 'fcm' : 'pushe',
            'provider_token' => $platform === 'ios' ? $token : null,
            'token_hash' => $platform === 'ios' ? hash('sha256', $token) : null,
            'locale' => $locale, 'enabled' => true, 'last_seen_at' => now()->subSeconds($age),
        ]);
    }

    public function test_ios_languages_are_independent_and_android_uses_latest_known_android_language(): void
    {
        $user = User::factory()->create();
        $this->device($user, 'android', 'en', 30);
        $this->device($user, 'android', 'fa_IR', 10);
        $this->device($user, 'android', null);
        $this->device($user, 'ios', 'en');
        $this->device($user, 'ios', 'fa-IR');
        $targets = app(PushNotificationTargetResolver::class)->forUser($user);
        $this->assertSame(['fa-ir', 'en', 'fa-ir'], array_column($targets, 'locale'));
        $this->assertCount(3, $targets); // One Pushe account group, two individual FCM devices.
        $this->assertNull($targets[0]->pushDeviceId);
    }

    public function test_announcement_uses_each_devices_translation_and_preserves_plain_copy_fallback(): void
    {
        $user = User::factory()->create();
        $this->device($user, 'ios', 'fa-IR');
        $this->device($user, 'ios', 'en');
        $provider = new class implements PushNotificationProvider
        {
            public array $messages = [];

            public function key(): string
            {
                return 'fcm';
            }

            public function send(PushNotificationTarget $target, PushNotificationMessage $message): PushNotificationDeliveryResult
            {
                $this->messages[$target->locale] = $message;

                return PushNotificationDeliveryResult::sent('test');
            }
        };
        $announcement = AppAnnouncement::create([
            'type' => 'info', 'presentation' => 'banner', 'title' => 'Fallback title', 'message' => 'Fallback message',
            'title_translations' => ['fa' => 'نسخهٔ جدید', 'en' => 'New version'],
            'message_translations' => ['fa' => 'اپ را به‌روز کنید', 'en' => 'Update the app'],
            'publish_push' => true, 'is_active' => true,
        ]);
        $resolver = app(PushNotificationTargetResolver::class);
        $registry = new PushProviderRegistry([$provider]);
        (new SendAppAnnouncementPushJob($announcement->id))->handle($resolver, $registry);
        $this->assertSame('نسخهٔ جدید', $provider->messages['fa-ir']->title);
        $this->assertSame('اپ را به‌روز کنید', $provider->messages['fa-ir']->body);
        $this->assertSame('New version', $provider->messages['en']->title);
        $announcement->update(['push_sent_at' => null, 'title_translations' => null, 'message_translations' => null]);
        (new SendAppAnnouncementPushJob($announcement->id))->handle($resolver, $registry);
        $this->assertSame('Fallback title', $provider->messages['fa-ir']->title);
    }
}
