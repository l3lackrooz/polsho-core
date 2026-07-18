<?php

namespace App\Domain\Market\Actions;

use App\Domain\Market\Application\DTO\MarketProviderDTO;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Infrastructure\Services\MarketDataService;
use Illuminate\Support\Facades\DB;
use Throwable;

class UpdateMarketProvider
{
    public function __construct(private readonly MarketDataService $marketData) {}

    public function execute(MarketProvider $provider, MarketProviderDTO $data): MarketProvider
    {
        $updated = DB::transaction(function () use ($provider, $data): MarketProvider {
            $provider->update($data->toArray());

            return $provider->refresh();
        });

        // Refresh the REST cache and Reverb payloads right after a display
        // metadata change; consumers should not need a new price tick.
        try {
            $instruments = $updated->markets()
                ->where('status', 'active')
                ->with('instrument:id,symbol')
                ->get()
                ->pluck('instrument.symbol')
                ->filter()
                ->unique();

            foreach ($instruments as $instrument) {
                $this->marketData->aggregate($instrument);
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return $updated;
    }
}
