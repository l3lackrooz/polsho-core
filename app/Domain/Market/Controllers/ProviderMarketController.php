<?php

namespace App\Domain\Market\Controllers;

use App\Domain\Market\Actions\CreateProviderMarket;
use App\Domain\Market\Actions\DeleteProviderMarket;
use App\Domain\Market\Actions\ListProviderMarkets;
use App\Domain\Market\Actions\UpdateProviderMarket;
use App\Domain\Market\Application\DTO\ProviderMarketDTO;
use App\Domain\Market\Application\DTO\ProviderMarketFiltersDTO;
use App\Domain\Market\Infrastructure\Persistence\Models\ProviderMarket;
use App\Domain\Market\Requests\StoreProviderMarketRequest;
use App\Domain\Market\Requests\UpdateProviderMarketRequest;
use App\Domain\Shared\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderMarketController extends Controller
{
    use RespondsWithApi;

    public function index(Request $request, ListProviderMarkets $action): JsonResponse
    {
        return $this->respondPaginated(
            $action->execute(ProviderMarketFiltersDTO::fromRequest($request))
        );
    }

    public function store(StoreProviderMarketRequest $request, CreateProviderMarket $action): JsonResponse
    {
        $data = ProviderMarketDTO::fromArray($request->validated());

        $providerMarket = $action->execute($data->toArray());

        return $this->respond(
            $providerMarket->load(['provider', 'instrument.baseAsset', 'instrument.quoteAsset']),
            201,
        );
    }

    public function show(ProviderMarket $providerMarket): JsonResponse
    {
        return $this->respond(
            $providerMarket->load(['provider', 'instrument.baseAsset', 'instrument.quoteAsset'])
        );
    }

    public function update(UpdateProviderMarketRequest $request, ProviderMarket $providerMarket, UpdateProviderMarket $action): JsonResponse
    {
        return $this->respond(
            $action->execute($providerMarket, ProviderMarketDTO::forUpdate($providerMarket, $request->validated()))
        );
    }

    public function destroy(ProviderMarket $providerMarket, DeleteProviderMarket $action): JsonResponse
    {
        $action->execute($providerMarket);

        return $this->respondMessage('Provider market removed.');
    }
}
