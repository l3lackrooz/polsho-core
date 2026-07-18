<?php

namespace App\Http\Controllers\Backoffice;

use App\Domain\Shared\Concerns\RespondsWithApi;
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
        return $this->respond(AppAnnouncement::query()->create($request->validated()), 201);
    }

    public function show(AppAnnouncement $announcement): JsonResponse
    {
        return $this->respond($announcement);
    }

    public function update(UpdateAppAnnouncementRequest $request, AppAnnouncement $announcement): JsonResponse
    {
        $announcement->update($request->validated());

        return $this->respond($announcement->refresh());
    }

    public function destroy(AppAnnouncement $announcement): JsonResponse
    {
        $announcement->delete();

        return $this->respondMessage('Announcement deleted.');
    }
}
