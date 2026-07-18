<?php

namespace App\Domain\Market\Controllers;

use App\Domain\Market\Actions\CreateMarketProvider;
use App\Domain\Market\Actions\DeleteMarketProvider;
use App\Domain\Market\Actions\ListAvailableDrivers;
use App\Domain\Market\Actions\ListMarketProviders;
use App\Domain\Market\Actions\SyncMarketProvider;
use App\Domain\Market\Actions\UpdateMarketProvider;
use App\Domain\Market\Application\DTO\MarketProviderDTO;
use App\Domain\Market\Application\DTO\MarketProviderFiltersDTO;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Requests\StoreMarketProviderRequest;
use App\Domain\Market\Requests\UpdateMarketProviderRequest;
use App\Domain\Shared\Concerns\RespondsWithApi;
use App\Domain\Shared\Services\BrandingStorage;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use DomainException;

class MarketProviderController extends Controller
{
    use RespondsWithApi;

    public function index(Request $request, ListMarketProviders $action): JsonResponse
    {
        return $this->respondPaginated(
            $action->execute(MarketProviderFiltersDTO::fromRequest($request))
        );
    }

    public function drivers(ListAvailableDrivers $action): JsonResponse
    {
        return $this->respond($action->execute());
    }

    public function store(StoreMarketProviderRequest $request, CreateMarketProvider $action): JsonResponse
    {
        $data = MarketProviderDTO::fromArray($request->validated());

        return $this->respond(
            $action->execute($data->toCreateAttributes()),
            201,
        );
    }

    public function show(MarketProvider $provider): JsonResponse
    {
        return $this->respond($provider->loadCount('markets'));
    }

    public function update(UpdateMarketProviderRequest $request, MarketProvider $provider, UpdateMarketProvider $action): JsonResponse
    {
        return $this->respond(
            $action->execute($provider, MarketProviderDTO::forUpdate($provider, $request->validated()))
        );
    }

    public function sync(MarketProvider $provider, SyncMarketProvider $action): JsonResponse
    {
        try {
            $action->execute($provider);
        } catch (DomainException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return $this->respondMessage('Provider sync queued.');
    }

    public function destroy(MarketProvider $provider, DeleteMarketProvider $action): JsonResponse
    {
        $action->execute($provider);

        return $this->respondMessage('Provider deleted.');
    }

    public function uploadLogo(Request $request, MarketProvider $provider, BrandingStorage $branding): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:512'],
        ]);

        $branding->delete($provider->logo_path);
        $provider->update(['logo_path' => $branding->store($request->file('logo'), $provider->slug)]);

        return $this->respond($provider->refresh());
    }

    public function deleteLogo(MarketProvider $provider, BrandingStorage $branding): JsonResponse
    {
        $branding->delete($provider->logo_path);
        $provider->update(['logo_path' => null]);

        return $this->respond($provider->refresh());
    }
}
