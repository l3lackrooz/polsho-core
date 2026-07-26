<?php

namespace App\Http\Controllers;

use App\Domain\Market\Application\Services\ProviderProfileVersion;
use App\Domain\Shared\Localization\LocalizedContent;
use App\Domain\Shared\Services\BrandingManifest;
use App\Models\AppAnnouncement;
use App\Models\AppVersionPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PublicAppStatusController extends Controller
{
    public function show(
        Request $request,
        BrandingManifest $branding,
        ProviderProfileVersion $providerProfiles,
    ): JsonResponse {
        $input = $request->validate([
            'platform' => ['required', Rule::in(['android', 'ios'])],
            'version' => ['required', 'string', 'max:32', 'regex:/^\d+(?:\.\d+){1,3}(?:[-+][0-9A-Za-z.-]+)?$/'],
            'build' => ['required', 'integer', 'min:1'],
            'locale' => ['nullable', 'string', 'max:12'],
        ]);
        $locale = LocalizedContent::locale($input['locale'] ?? null, $request->header('Accept-Language'));

        $announcements = AppAnnouncement::query()
            ->activeFor($input['platform'])
            ->orderByDesc('priority')
            ->latest('id')
            ->get()
            ->map(fn (AppAnnouncement $announcement): array => $this->announcement($announcement, $locale))
            ->values();
        $policy = AppVersionPolicy::query()
            ->where('platform', $input['platform'])
            ->where('is_active', true)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'announcements' => $announcements,
                'update' => $policy?->updateStatusFor($input['version'], (int) $input['build']) ?? ['mode' => 'none'],
                // Piggybacked so the app can detect branding changes without
                // an extra startup request; fetch /pub/branding only on change.
                'branding_version' => $branding->version(),
                // Provider profiles are stored locally by Flutter. This moving
                // version lets a publish or Backoffice cache refresh invalidate
                // them without a terminal command or an app release.
                'provider_profiles_version' => $providerProfiles->current(),
            ],
        ]);
    }

    /** @return array<string, bool|int|string|null> */
    private function announcement(AppAnnouncement $announcement, string $locale): array
    {
        return [
            'id' => $announcement->id,
            'presentation' => $announcement->presentation,
            'type' => $announcement->type,
            'title' => LocalizedContent::text($announcement->title_translations, $locale) ?? $announcement->title,
            'message' => LocalizedContent::text($announcement->message_translations, $locale) ?? $announcement->message,
            'action_label' => LocalizedContent::text($announcement->action_label_translations, $locale) ?? $announcement->action_label,
            'action_url' => $announcement->action_url,
            'is_dismissible' => $announcement->is_dismissible,
            'priority' => $announcement->priority,
        ];
    }
}
