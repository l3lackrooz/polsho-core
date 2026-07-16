<?php

namespace App\Domain\Market\Controllers;

use App\Domain\Market\Application\Presenters\PushDevicePresenter;
use App\Domain\Market\Infrastructure\Persistence\Models\PushDevice;
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
        PushDevice::query()
            ->where('user_id', $request->user()->id)
            ->where('installation_id', $installationId)
            ->update([
                'provider_token' => null,
                'token_hash' => null,
                'enabled' => false,
                'invalidated_at' => now(),
            ]);

        return response()->noContent();
    }
}
