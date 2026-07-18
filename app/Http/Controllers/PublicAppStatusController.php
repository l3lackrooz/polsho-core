<?php

namespace App\Http\Controllers;

use App\Domain\Shared\Services\BrandingManifest;
use App\Models\AppAnnouncement;
use App\Models\AppVersionPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PublicAppStatusController extends Controller
{
    public function show(Request $request, BrandingManifest $branding): JsonResponse
    {
        $input = $request->validate([
            'platform' => ['required', Rule::in(['android', 'ios'])],
            'version' => ['required', 'string', 'max:32', 'regex:/^\d+(?:\.\d+){1,3}(?:[-+][0-9A-Za-z.-]+)?$/'],
            'build' => ['required', 'integer', 'min:1'],
        ]);

        $announcements = AppAnnouncement::query()
            ->activeFor($input['platform'])
            ->orderByDesc('priority')
            ->latest('id')
            ->get()
            ->map(fn (AppAnnouncement $announcement): array => $this->announcement($announcement))
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
            ],
        ]);
    }

    /** @return array<string, bool|int|string|null> */
    private function announcement(AppAnnouncement $announcement): array
    {
        return [
            'id' => $announcement->id,
            'presentation' => $announcement->presentation,
            'type' => $announcement->type,
            'title' => $announcement->title,
            'message' => $announcement->message,
            'action_label' => $announcement->action_label,
            'action_url' => $announcement->action_url,
            'is_dismissible' => $announcement->is_dismissible,
            'priority' => $announcement->priority,
        ];
    }
}
