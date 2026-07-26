<?php

namespace App\Domain\Market\Controllers;

use App\Domain\Market\Application\Presenters\PublicMarketProviderProfilePresenter;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Shared\Concerns\RespondsWithApi;
use App\Domain\Shared\Localization\LocalizedContent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PublicMarketProviderProfileController extends Controller
{
    use RespondsWithApi;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['nullable', 'string', 'max:12'],
            'type' => ['nullable', Rule::in(['exchange', 'reference_source'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $locale = LocalizedContent::locale($validated['locale'] ?? null, $request->header('Accept-Language'));

        $profiles = MarketProvider::query()
            ->where('status', 'active')
            ->whereHas('profile', function ($query) use ($validated): void {
                $query->where('publication_status', 'published');
                if (isset($validated['type'])) {
                    $query->where('type', $validated['type']);
                }
            })
            ->with('profile')
            ->withCount(['markets as active_markets_count' => function ($query): void {
                $query->where('status', 'active');
            }])
            ->orderBy('priority')
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 20);

        $profiles->setCollection(
            $profiles->getCollection()->map(
                fn (MarketProvider $provider): array => PublicMarketProviderProfilePresenter::card($provider, $locale),
            ),
        );

        return $this->respondPaginated($profiles);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $locale = LocalizedContent::locale($request->query('locale'), $request->header('Accept-Language'));
        $provider = MarketProvider::query()
            ->where('slug', $slug)
            ->where('status', 'active')
            ->whereHas('profile', fn ($query) => $query->where('publication_status', 'published'))
            ->with(['profile', 'markets.instrument'])
            ->withCount(['markets as active_markets_count' => function ($query): void {
                $query->where('status', 'active');
            }])
            ->firstOrFail();

        return $this->respond(PublicMarketProviderProfilePresenter::detail($provider, $locale));
    }
}
