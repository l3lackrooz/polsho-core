<?php

namespace App\Http\Controllers\Backoffice;

use App\Domain\Shared\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppVersionPolicyRequest;
use App\Http\Requests\UpdateAppVersionPolicyRequest;
use App\Models\AppVersionPolicy;
use Illuminate\Http\JsonResponse;

class AppVersionPolicyController extends Controller
{
    use RespondsWithApi;

    public function index(): JsonResponse
    {
        return $this->respond(AppVersionPolicy::query()->orderBy('platform')->get());
    }

    public function store(StoreAppVersionPolicyRequest $request): JsonResponse
    {
        return $this->respond(AppVersionPolicy::query()->create($request->validated()), 201);
    }

    public function show(AppVersionPolicy $versionPolicy): JsonResponse
    {
        return $this->respond($versionPolicy);
    }

    public function update(UpdateAppVersionPolicyRequest $request, AppVersionPolicy $versionPolicy): JsonResponse
    {
        $versionPolicy->update($request->validated());

        return $this->respond($versionPolicy->refresh());
    }

    public function destroy(AppVersionPolicy $versionPolicy): JsonResponse
    {
        $versionPolicy->delete();

        return $this->respondMessage('Version policy deleted.');
    }
}
