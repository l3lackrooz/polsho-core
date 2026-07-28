<?php

namespace App\Domain\Market\Controllers;

use App\Domain\Market\Application\Presenters\PushDevicePresenter;
use App\Domain\Market\Infrastructure\Persistence\Models\LiveActivityPushToken;
use App\Domain\Market\Infrastructure\Persistence\Models\PriceAlert;
use App\Domain\Market\Infrastructure\Persistence\Models\PushDevice;
use App\Domain\Market\Requests\UpsertLiveActivityPushTokenRequest;
use App\Domain\Market\Requests\UpsertPushDeviceRequest;
use App\Domain\Shared\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class PushDeviceController extends Controller
{
    use RespondsWithApi;

    public function upsert(
        UpsertPushDeviceRequest $request,
        string $installationId,
        PushDevicePresenter $presenter,
    ): JsonResponse {
        $data = $request->validated();
        $token = $data['provider_token'] ?? null;
        $tokenHash = is_string($token) ? hash('sha256', $token) : null;

        $device = DB::transaction(function () use ($request, $data, $installationId, $token, $tokenHash): PushDevice {
            $device = PushDevice::query()
                ->where('installation_id', $installationId)
                ->lockForUpdate()
                ->first() ?? new PushDevice(['installation_id' => $installationId]);

            if ($tokenHash !== null) {
                PushDevice::query()
                    ->where('token_hash', $tokenHash)
                    ->when($device->exists, fn ($query) => $query->whereKeyNot($device->getKey()))
                    ->update([
                        'provider_token' => null,
                        'token_hash' => null,
                        'enabled' => false,
                        'invalidated_at' => now(),
                    ]);
            }

            $device->fill([
                'user_id' => $request->user()->id,
                'platform' => $data['platform'],
                'provider' => $data['provider'],
                'provider_token' => $token,
                'token_hash' => $tokenHash,
                'enabled' => true,
                'app_version' => $data['app_version'] ?? null,
                'locale' => $data['locale'] ?? null,
                'last_seen_at' => now(),
                'invalidated_at' => null,
            ]);
            $device->save();

            return $device;
        });

        return $this->respond($presenter->present($device), $device->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, string $installationId): Response
    {
        $devices = PushDevice::query()
            ->where('user_id', $request->user()->id)
            ->where('installation_id', $installationId)
            ->get();

        foreach ($devices as $device) {
            $device->update([
                'provider_token' => null,
                'token_hash' => null,
                'enabled' => false,
                'invalidated_at' => now(),
            ]);
            $device->liveActivityPushTokens()->update([
                'enabled' => false,
                'invalidated_at' => now(),
            ]);
        }

        return response()->noContent();
    }

    public function upsertLiveActivityToken(
        UpsertLiveActivityPushTokenRequest $request,
        string $installationId,
    ): JsonResponse {
        $data = $request->validated();
        $tokenHash = hash('sha256', $data['token']);

        if (isset($data['price_alert_id']) && ! PriceAlert::query()
            ->whereKey($data['price_alert_id'])
            ->where('user_id', $request->user()->id)
            ->exists()) {
            abort(422, 'The Live Activity alert must belong to the current user.');
        }

        $token = DB::transaction(function () use ($request, $data, $installationId, $tokenHash): LiveActivityPushToken {
            $device = PushDevice::query()
                ->where('user_id', $request->user()->id)
                ->where('installation_id', $installationId)
                ->where('platform', 'ios')
                ->where('provider', 'fcm')
                ->where('enabled', true)
                ->lockForUpdate()
                ->firstOrFail();

            LiveActivityPushToken::query()
                ->where('token_hash', $tokenHash)
                ->where('push_device_id', '!=', $device->id)
                ->update([
                    'enabled' => false,
                    'invalidated_at' => now(),
                ]);

            $query = LiveActivityPushToken::query()
                ->where('push_device_id', $device->id)
                ->where('kind', $data['kind']);

            if ($data['kind'] === 'push_to_start') {
                $query->whereNull('activity_id');
            } else {
                $query->where('activity_id', $data['activity_id']);
            }

            $record = $query->lockForUpdate()->first()
                ?? new LiveActivityPushToken([
                    'push_device_id' => $device->id,
                    'kind' => $data['kind'],
                    'activity_id' => $data['activity_id'] ?? null,
                    'price_alert_id' => $data['price_alert_id'] ?? null,
                ]);

            $record->fill([
                'token' => $data['token'],
                'token_hash' => $tokenHash,
                'enabled' => true,
                'last_seen_at' => now(),
                'invalidated_at' => null,
            ]);
            $record->save();

            return $record;
        });

        return $this->respond([
            'kind' => $token->kind,
            'activity_id' => $token->activity_id,
            'price_alert_id' => $token->price_alert_id,
            'enabled' => $token->enabled,
            'last_seen_at' => $token->last_seen_at?->toISOString(),
        ], 200);
    }
}
