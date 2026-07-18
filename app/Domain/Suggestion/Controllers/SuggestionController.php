<?php

namespace App\Domain\Suggestion\Controllers;

use App\Domain\Shared\Concerns\RespondsWithApi;
use App\Domain\Suggestion\Application\Presenters\SuggestionPresenter;
use App\Domain\Suggestion\Application\Services\SuggestionService;
use App\Domain\Suggestion\Infrastructure\Persistence\Models\Suggestion;
use App\Domain\Suggestion\Requests\StoreSuggestionRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * App-facing endpoints: a signed-in user submits requests and reviews the
 * status of the ones they have sent.
 */
class SuggestionController extends Controller
{
    use RespondsWithApi;

    public function index(Request $request, SuggestionService $service, SuggestionPresenter $presenter): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->input('per_page', 25)));

        $suggestions = $service->listForUser($request->user()->id, $perPage);

        return response()->json([
            'success' => true,
            'data' => $suggestions->getCollection()
                ->map(fn (Suggestion $suggestion): array => $presenter->present($suggestion))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $suggestions->currentPage(),
                'per_page' => $suggestions->perPage(),
                'total' => $suggestions->total(),
                'last_page' => $suggestions->lastPage(),
            ],
        ]);
    }

    public function store(StoreSuggestionRequest $request, SuggestionService $service, SuggestionPresenter $presenter): JsonResponse
    {
        $suggestion = $service->create($request->user()?->id, $request->validated());

        return $this->respond($presenter->present($suggestion), 201);
    }
}
