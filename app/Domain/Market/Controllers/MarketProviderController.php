<?php

namespace App\Domain\Market\Controllers;

use App\Domain\Market\Actions\CreateMarketProvider;
use App\Domain\Market\Actions\DeleteMarketProvider;
use App\Domain\Market\Actions\ListAvailableDrivers;
use App\Domain\Market\Actions\ListMarketProviders;
use App\Domain\Market\Actions\UpdateMarketProvider;
use App\Domain\Market\Application\DTO\MarketProviderDTO;
use App\Domain\Market\Application\DTO\MarketProviderFiltersDTO;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Requests\StoreMarketProviderRequest;
use App\Domain\Market\Requests\UpdateMarketProviderRequest;
use App\Domain\Shared\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function destroy(MarketProvider $provider, DeleteMarketProvider $action): JsonResponse
    {
        $action->execute($provider);

        return $this->respondMessage('Provider deleted.');
    }
}
