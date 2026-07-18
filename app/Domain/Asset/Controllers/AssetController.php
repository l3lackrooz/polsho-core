<?php

namespace App\Domain\Asset\Controllers;

use App\Domain\Asset\Actions\CreateAsset;
use App\Domain\Asset\Actions\DeleteAsset;
use App\Domain\Asset\Actions\ListAssets;
use App\Domain\Asset\Actions\UpdateAsset;
use App\Domain\Asset\Application\DTO\AssetDTO;
use App\Domain\Asset\Application\DTO\AssetFiltersDTO;
use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Asset\Requests\StoreAssetRequest;
use App\Domain\Asset\Requests\UpdateAssetRequest;
use App\Domain\Shared\Concerns\RespondsWithApi;
use App\Domain\Shared\Services\BrandingStorage;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    use RespondsWithApi;

    public function index(Request $request, ListAssets $action): JsonResponse
    {
        return $this->respondPaginated(
            $action->execute(AssetFiltersDTO::fromRequest($request))
        );
    }

    public function store(StoreAssetRequest $request, CreateAsset $action): JsonResponse
    {
        return $this->respond(
            $action->execute(AssetDTO::fromArray($request->validated())),
            201,
        );
    }

    public function show(Asset $asset): JsonResponse
    {
        return $this->respond($asset);
    }

    public function update(UpdateAssetRequest $request, Asset $asset, UpdateAsset $action): JsonResponse
    {
        return $this->respond(
            $action->execute($asset, AssetDTO::forUpdate($asset, $request->validated()))
        );
    }

    public function destroy(Asset $asset, DeleteAsset $action): JsonResponse
    {
        $action->execute($asset);

        return $this->respondMessage('Asset deleted.');
    }

    public function uploadLogo(Request $request, Asset $asset, BrandingStorage $branding): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:512'],
        ]);

        $branding->delete($asset->logo_path);
        $asset->update(['logo_path' => $branding->store($request->file('logo'), $asset->symbol)]);

        return $this->respond($asset->refresh());
    }

    public function deleteLogo(Asset $asset, BrandingStorage $branding): JsonResponse
    {
        $branding->delete($asset->logo_path);
        $asset->update(['logo_path' => null]);

        return $this->respond($asset->refresh());
    }
}
