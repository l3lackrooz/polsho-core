<?php

namespace App\Http\Controllers\Backoffice;

use App\Domain\Shared\Concerns\RespondsWithApi;
use App\Domain\Market\Application\Jobs\SendAppAnnouncementPushJob;
use App\Domain\Shared\Localization\LocalizedContent;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppAnnouncementRequest;
use App\Http\Requests\UpdateAppAnnouncementRequest;
use App\Models\AppAnnouncement;
use Illuminate\Http\JsonResponse;

class AppAnnouncementController extends Controller
{
    use RespondsWithApi;

    public function index(): JsonResponse
    {
        return $this->respond(AppAnnouncement::query()->latest('id')->get());
    }

    public function store(StoreAppAnnouncementRequest $request): JsonResponse
    {
        $announcement = AppAnnouncement::query()->create($this->payload($request->validated()));
        if ($announcement->publish_push) SendAppAnnouncementPushJob::dispatch($announcement->id);

        return $this->respond($announcement, 201);
    }

    public function show(AppAnnouncement $announcement): JsonResponse
    {
        return $this->respond($announcement);
    }

    public function update(UpdateAppAnnouncementRequest $request, AppAnnouncement $announcement): JsonResponse
    {
        $announcement->update($this->payload($request->validated(), $announcement));

        return $this->respond($announcement->refresh());
    }

    public function publishPush(AppAnnouncement $announcement): JsonResponse
    {
        $announcement->update(['publish_push' => true, 'push_status' => 'pending', 'push_sent_at' => null]);
        SendAppAnnouncementPushJob::dispatch($announcement->id);

        return $this->respond($announcement->refresh());
    }

    public function destroy(AppAnnouncement $announcement): JsonResponse
    {
        $announcement->delete();

        return $this->respondMessage('Announcement deleted.');
    }

    /** @param array<string, mixed> $attributes */
    private function payload(array $attributes, ?AppAnnouncement $current = null): array
    {
        foreach (['title', 'message', 'action_label'] as $field) {
            $translationsField = "{$field}_translations";
            if (array_key_exists($translationsField, $attributes)) {
                $attributes[$translationsField] = LocalizedContent::map($attributes[$translationsField]);
            }

            if (array_key_exists($translationsField, $attributes) || ! array_key_exists($field, $attributes)) {
                $translations = $attributes[$translationsField] ?? $current?->{$translationsField};
                $fallback = $current?->{$field};
                $attributes[$field] = LocalizedContent::text($translations, 'fa')
                    ?? LocalizedContent::text($translations, 'en')
                    ?? $fallback;
            }
        }

        return $attributes;
    }
}
