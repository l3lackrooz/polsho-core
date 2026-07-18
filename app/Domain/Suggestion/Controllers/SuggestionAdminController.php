<?php

namespace App\Domain\Suggestion\Controllers;

use App\Domain\Shared\Concerns\RespondsWithApi;
use App\Domain\Suggestion\Application\Presenters\SuggestionPresenter;
use App\Domain\Suggestion\Application\Services\SuggestionService;
use App\Domain\Suggestion\Infrastructure\Persistence\Models\Suggestion;
use App\Domain\Suggestion\Requests\ReviewSuggestionRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backoffice triage queue. Guarded by auth:sanctum + EnsureAdmin at the route.
 */
class SuggestionAdminController extends Controller
{
    use RespondsWithApi;

    public function index(Request $request, SuggestionService $service, SuggestionPresenter $presenter): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->input('per_page', 25)));

        $suggestions = $service->listForAdmin([
            'status' => $request->input('status'),
            'type' => $request->input('type'),
            'search' => $request->input('search'),
        ], $perPage);

        return response()->json([
            'success' => true,
            'data' => $suggestions->getCollection()
                ->map(fn (Suggestion $suggestion): array => $presenter->presentForAdmin($suggestion))
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

    public function show(Suggestion $suggestion, SuggestionPresenter $presenter): JsonResponse
    {
        return $this->respond($presenter->presentForAdmin($suggestion->load('user')));
    }

    public function update(ReviewSuggestionRequest $request, Suggestion $suggestion, SuggestionService $service, SuggestionPresenter $presenter): JsonResponse
    {
        $updated = $service->review($suggestion, $request->validated());

        return $this->respond($presenter->presentForAdmin($updated->load('user')));
    }

    public function destroy(Suggestion $suggestion, SuggestionService $service): JsonResponse
    {
        $service->delete($suggestion);

        return $this->respondMessage('Suggestion deleted.');
    }
}
