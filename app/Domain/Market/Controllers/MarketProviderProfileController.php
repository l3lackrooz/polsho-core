<?php

namespace App\Domain\Market\Controllers;

use App\Domain\Market\Actions\UpsertMarketProviderProfile;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Requests\UpsertMarketProviderProfileRequest;
use App\Domain\Shared\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MarketProviderProfileController extends Controller
{
    use RespondsWithApi;

    public function show(MarketProvider $provider): JsonResponse
    {
        return $this->respond($provider->profile);
    }

    public function upsert(
        UpsertMarketProviderProfileRequest $request,
        MarketProvider $provider,
        UpsertMarketProviderProfile $action,
    ): JsonResponse {
        return $this->respond($action->execute($provider, $request->validated()));
    }

    public function destroy(MarketProvider $provider): JsonResponse
    {
        $profile = $provider->profile()->firstOrFail();
        $profile->delete();

        return $this->respondMessage('Provider profile deleted.');
    }
}
