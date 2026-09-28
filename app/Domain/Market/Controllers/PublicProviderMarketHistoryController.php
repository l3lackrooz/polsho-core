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
        $end = now();
        [$start, $bucket, $ttl] = match ($range) {
            '1h' => [$end->copy()->subHour(), 60, 60],
            '7d' => [$end->copy()->subDays(7), 3600, 900],
            '30d' => [$end->copy()->subDays(30), 21600, 3600],
            default => [$end->copy()->subDay(), 300, 300],
        };
        $key = "provider-history:v2:{$providerMarket->id}:{$range}";
        $data = Cache::remember($key, $ttl, function () use ($providerMarket, $start, $end, $bucket): array {
            $rows = DB::table('market_snapshots')
                ->where('provider_market_id', $providerMarket->id)
                ->whereBetween('captured_at', [$start, $end])
                ->orderBy('captured_at')->orderBy('id')
                ->select(['captured_at', 'bid', 'ask', 'last_price'])->cursor();
            $points = [];
            foreach ($rows as $row) {
                $time = \Carbon\Carbon::parse($row->captured_at)->timestamp;
                $slot = intdiv($time, $bucket) * $bucket;
                $buy = $this->price($row->ask) ?? $this->price($row->last_price);
                $sell = $this->price($row->bid) ?? $this->price($row->last_price);
                $price = $this->price($row->last_price)
                    ?? ($buy !== null && $sell !== null ? ($buy + $sell) / 2 : $buy ?? $sell);
                if ($price === null) {
                    continue;
                }
                // Sample the last quote in each bucket, but retain its real time.
                // A 30-day bucket must not move a recent quote six hours backward.
                $points[$slot] = ['timestamp' => $time * 1000, 'price' => $price, 'buy' => $buy, 'sell' => $sell];
            }

            return array_values($points);
        });
        // Cached windows slide too: never return a point outside the current range.
        $data = array_values(array_filter($data, fn (array $point): bool => $point['timestamp'] >= $start->getTimestampMs() && $point['timestamp'] <= $end->getTimestampMs()
        ));

        return $this->respond([
            'range' => $range,
            'instrument' => $providerMarket->instrument?->symbol,
            'points' => $data,
            'latest_timestamp' => $data === [] ? null : $data[array_key_last($data)]['timestamp'],
            'window_start' => $start->getTimestampMs(),
            'window_end' => $end->getTimestampMs(),
            'bucket_seconds' => $bucket,
        ]);
    }

    private function price(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }
        $price = (float) $value;

        return is_finite($price) && $price > 0 ? $price : null;
    }
}
