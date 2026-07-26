<?php

namespace App\Domain\Market\Controllers;

use App\Domain\Market\Infrastructure\Persistence\Models\ProviderMarket;
use App\Domain\Shared\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PublicProviderMarketHistoryController extends Controller
{
    use RespondsWithApi;

    public function show(Request $request, ProviderMarket $providerMarket): JsonResponse
    {
        $providerMarket->loadMissing('instrument:id,symbol');
        abort_unless($providerMarket->status === 'active' && $providerMarket->provider()->where('status', 'active')->exists(), 404);
        $range = $request->validate(['range' => ['nullable', 'in:1h,24h,7d,30d']])['range'] ?? '24h';
        [$start, $bucket, $ttl] = match ($range) {
            '1h' => [now()->subHour(), 60, 60], '7d' => [now()->subDays(7), 3600, 900], '30d' => [now()->subDays(30), 21600, 3600], default => [now()->subDay(), 300, 300],
        };
        $key = "provider-history:{$providerMarket->id}:{$range}";
        $data = Cache::remember($key, $ttl, function () use ($providerMarket, $start, $bucket): array {
            $rows = DB::table('market_snapshots')->where('provider_market_id', $providerMarket->id)->where('captured_at', '>=', $start)->orderBy('captured_at')->get(['captured_at', 'bid', 'ask', 'last_price']);
            $points = [];
            foreach ($rows as $row) {
                $time = \Carbon\Carbon::parse($row->captured_at)->timestamp;
                $slot = intdiv($time, $bucket) * $bucket;
                $points[$slot] = ['timestamp' => $slot * 1000, 'price' => $row->last_price ?? ($row->bid !== null && $row->ask !== null ? ((float) $row->bid + (float) $row->ask) / 2 : $row->ask ?? $row->bid)];
                $points[$slot]['buy'] = $row->ask ?? $row->last_price;
                $points[$slot]['sell'] = $row->bid ?? $row->last_price;
            }

            return array_values($points);
        });

        return $this->respond(['range' => $range, 'instrument' => $providerMarket->instrument?->symbol, 'points' => $data, 'latest_timestamp' => $data === [] ? null : $data[array_key_last($data)]['timestamp']]);
    }
}
