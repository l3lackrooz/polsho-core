<?php

namespace App\Domain\Market\Controllers;

use App\Domain\Market\Application\Presenters\PriceAlertPresenter;
use App\Domain\Market\Application\Services\PriceAlertService;
use App\Domain\Market\Infrastructure\Persistence\Models\PriceAlert;
use App\Domain\Shared\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PriceAlertController extends Controller
{
    use RespondsWithApi;

    public function index(Request $request, PriceAlertService $service, PriceAlertPresenter $presenter): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->input('per_page', 25)));

        $alerts = $service->list($request->user()->id, $perPage);

        return response()->json([
            'success' => true,
            'data' => $alerts->getCollection()
                ->map(fn (PriceAlert $alert): array => $presenter->present($alert))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $alerts->currentPage(),
                'per_page' => $alerts->perPage(),
                'total' => $alerts->total(),
                'last_page' => $alerts->lastPage(),
            ],
        ]);
    }

    public function store(Request $request, PriceAlertService $service, PriceAlertPresenter $presenter): JsonResponse
    {
        return $this->respond($presenter->present($service->create($request->user()->id, $this->validated($request))), 201);
    }

    public function show(Request $request, PriceAlert $priceAlert, PriceAlertPresenter $presenter): JsonResponse
    {
        $this->authorizeOwner($request, $priceAlert);

        return $this->respond($presenter->present($priceAlert->load(['instrument.baseAsset', 'instrument.quoteAsset', 'providerMarket.provider', 'events'])));
    }

    public function update(Request $request, PriceAlert $priceAlert, PriceAlertService $service, PriceAlertPresenter $presenter): JsonResponse
    {
        $this->authorizeOwner($request, $priceAlert);

        return $this->respond($presenter->present($service->update($priceAlert, $this->validated($request, true))));
    }

    public function destroy(Request $request, PriceAlert $priceAlert, PriceAlertService $service): JsonResponse
    {
        $this->authorizeOwner($request, $priceAlert);
        $service->delete($priceAlert);

        return $this->respondMessage('Price alert removed.');
    }

    private function authorizeOwner(Request $request, PriceAlert $alert): void
    {
        abort_unless((int) $alert->user_id === (int) $request->user()->id, 404);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate(['instrument_id' => [$required, 'integer', 'exists:instruments,id'], 'provider_market_id' => ['nullable', 'integer', 'exists:provider_markets,id'], 'scope' => [$required, Rule::in(['best_market', 'specific_exchange'])], 'condition' => [$required, Rule::in(['reaches', 'goes_above', 'goes_below'])], 'target_price' => [$required, 'numeric', 'gt:0'], 'status' => ['sometimes', Rule::in(['active', 'paused', 'triggered', 'expired'])], 'repeat' => ['sometimes', Rule::in(['once', 'recurring'])], 'notify_push' => ['sometimes', 'boolean'], 'notify_in_app' => ['sometimes', 'boolean'], 'expires_at' => ['nullable', 'date'], 'metadata' => ['nullable', 'array']]);
    }
}
