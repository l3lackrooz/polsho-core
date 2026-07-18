<?php

namespace App\Domain\Market\Application\Services;

use App\Domain\Market\Application\Presenters\PriceAlertPresenter;
use App\Domain\Market\Infrastructure\Persistence\Models\PriceAlert;
use App\Domain\Market\Infrastructure\Persistence\Models\ProviderMarket;
use App\Models\User;
use App\Services\AlertEntitlementService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PriceAlertService
{
    public function __construct(
        private readonly AlertEntitlementService $entitlements,
        private readonly PriceAlertPresenter $presenter,
    ) {}

    public function list(int $userId, int $perPage = 25): LengthAwarePaginator
    {
        return PriceAlert::query()->with(['instrument.baseAsset', 'instrument.quoteAsset', 'providerMarket.provider', 'events'])->where('user_id', $userId)->latest()->paginate($perPage);
    }

    public function create(int $userId, array $data): PriceAlert
    {
        return DB::transaction(function () use ($userId, $data) {
            $user = User::query()->lockForUpdate()->findOrFail($userId);
            $this->entitlements->assertCanCreate($user);
            $this->validateScope($data);
            $alert = PriceAlert::create([...$data, 'user_id' => $userId]);
            // Anchor progress displays to the market price at creation.
            $baseline = $this->presenter->currentPrice($this->load($alert));
            if ($baseline !== null) {
                $alert->update(['baseline_price' => $baseline]);
            }
            $alert->events()->create(['type' => 'created', 'occurred_at' => now()]);

            return $this->load($alert);
        });
    }

    public function update(PriceAlert $alert, array $data): PriceAlert
    {
        return DB::transaction(function () use ($alert, $data) {
            $payload = [...$alert->only(['instrument_id', 'provider_market_id', 'scope']), ...$data];
            $this->validateScope($payload);
            $alert->update($data);
            $alert->events()->create(['type' => 'edited', 'payload' => ['changed' => array_keys($data)], 'occurred_at' => now()]);

            return $this->load($alert->refresh());
        });
    }

    public function delete(PriceAlert $alert): void
    {
        $alert->delete();
    }

    public function setStatus(PriceAlert $alert, string $status): PriceAlert
    {
        $alert->update(['status' => $status]);
        $alert->events()->create(['type' => $status === 'paused' ? 'paused' : 'resumed', 'occurred_at' => now()]);

        return $this->load($alert->refresh());
    }

    private function load(PriceAlert $alert): PriceAlert
    {
        return $alert->load(['instrument.baseAsset', 'instrument.quoteAsset', 'providerMarket.provider', 'events']);
    }

    private function validateScope(array $data): void
    {
        if (($data['scope'] ?? null) !== 'specific_exchange') {
            return;
        } $market = ProviderMarket::query()->find($data['provider_market_id'] ?? null);
        if ($market === null || (int) $market->instrument_id !== (int) ($data['instrument_id'] ?? 0)) {
            throw ValidationException::withMessages(['provider_market_id' => 'The selected exchange must support this instrument.']);
        }
    }
}
