<?php

namespace App\Domain\Market\Controllers;

use App\Domain\Market\Actions\ListPublicQuotes;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicQuoteController extends Controller
{
    public function index(Request $request, ListPublicQuotes $action): JsonResponse
    {
        $symbols = $this->symbolsFromRequest($request);

        if ($symbols === []) {
            return response()->json([
                'success' => false,
                'message' => 'Provide between one and fifty instrument symbols.',
            ], 422);
        }

        $result = $action->execute($symbols);

        return response()->json([
            'success' => true,
            'data' => $result['quotes'],
            'meta' => [
                'requested' => $symbols,
                'missing' => $result['missing'],
                'timestamp' => now()->valueOf(),
            ],
        ]);
    }

    /** @return string[] */
    private function symbolsFromRequest(Request $request): array
    {
        $rawSymbols = $request->input('instruments', '');
        $symbols = is_array($rawSymbols)
            ? $rawSymbols
            : explode(',', (string) $rawSymbols);

        $normalized = array_values(array_unique(array_filter(array_map(
            fn (mixed $symbol): string => strtoupper(trim((string) $symbol)),
            $symbols,
        ))));

        if (count($normalized) > 50 || array_filter(
            $normalized,
            fn (string $symbol): bool => ! preg_match('/^[A-Z0-9-]{1,64}$/', $symbol),
        )) {
            return [];
        }

        return $normalized;
    }
}
