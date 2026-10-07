<?php

namespace App\Domain\Market\Controllers;

use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Shared\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicComparisonProviderController extends Controller
{
    use RespondsWithApi;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'instrument' => ['sometimes', 'string', 'max:64', 'regex:/^[a-zA-Z0-9]+-[a-zA-Z0-9]+$/'],
        ]);
        $symbol = strtoupper($validated['instrument'] ?? 'USDT-IRT');
        // Catalog eligibility uses the app's toman display market. No prices are
        // returned here: IRR quotes must still be divided by ten for display.
        [$base, $quote] = explode('-', $symbol);
        $sourceSymbols = $quote === 'IRT' ? [$symbol, $base.'-IRR'] : [$symbol];
        $providers = MarketProvider::query()
            ->where('status', 'active')
            ->where('comparison_enabled', true)
            ->whereHas('markets', function ($markets) use ($sourceSymbols): void {
                $markets->where('status', 'active')
                    ->whereHas('instrument', function ($instruments) use ($sourceSymbols): void {
                        $instruments->where('status', 'active')
                            ->whereIn(\Illuminate\Support\Facades\DB::raw('UPPER(symbol)'), $sourceSymbols);
                    });
            })
            ->orderBy('priority')->orderBy('name')
            ->get()
            ->reject(fn (MarketProvider $provider): bool => (bool) data_get($provider->config, 'is_reference', false))
            // Explicit public allowlist: never return driver/config/API credentials.
            ->map(fn (MarketProvider $provider): array => [
                'slug' => $provider->slug,
                'name' => $provider->name,
                'translations' => $provider->translations ?? (object) [],
                'logo_url' => $provider->logo_url,
                'demo_enabled' => $provider->demo_enabled,
            ])->values();

        return $this->respond(['instrument' => $symbol, 'providers' => $providers])
            ->header('Cache-Control', 'no-store');
    }
}
