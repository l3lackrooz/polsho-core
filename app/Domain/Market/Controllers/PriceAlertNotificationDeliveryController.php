<?php

namespace App\Domain\Market\Controllers;

use App\Domain\Market\Application\Jobs\SendPriceAlertNotificationJob;
use App\Domain\Market\Application\Jobs\SendPriceAlertPushJob;
use App\Domain\Market\Application\Presenters\PriceAlertNotificationDeliveryPresenter;
use App\Domain\Market\Application\Services\PriceAlertPushDeliveryService;
use App\Domain\Market\Infrastructure\Persistence\Models\PriceAlertNotificationDelivery;
use App\Domain\Shared\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PriceAlertNotificationDeliveryController extends Controller
{
    use RespondsWithApi;

    public function index(Request $request, PriceAlertNotificationDeliveryPresenter $presenter): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'sent', 'failed', 'skipped'])],
            'search' => ['nullable', 'string', 'max:255'],
        ]);
        $perPage = min(100, max(1, (int) $request->input('per_page', 25)));

        $deliveries = PriceAlertNotificationDelivery::query()
            ->with(['event.alert.instrument', 'event.alert.user', 'pushDeliveries.pushDevice'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('push_status', $status))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): Builder {
                return $query->whereHas('event.alert', function (Builder $alerts) use ($search): void {
                    $alerts->whereHas('instrument', fn (Builder $instruments): Builder => $instruments->where('symbol', 'like', "%{$search}%"))
                        ->orWhereHas('user', fn (Builder $users): Builder => $users->where('email', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $deliveries->getCollection()
                ->map(fn (PriceAlertNotificationDelivery $delivery): array => $presenter->present($delivery))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $deliveries->currentPage(),
                'per_page' => $deliveries->perPage(),
                'total' => $deliveries->total(),
                'last_page' => $deliveries->lastPage(),
            ],
        ]);
    }

    public function retry(
        PriceAlertNotificationDelivery $delivery,
        PriceAlertNotificationDeliveryPresenter $presenter,
        PriceAlertPushDeliveryService $pushDeliveries,
    ): JsonResponse {
        $delivery->load('pushDeliveries.pushDevice');
        $failedTargets = $delivery->pushDeliveries->where('status', 'failed');
        $isLegacyFailure = $delivery->push_status === 'failed' && $delivery->pushDeliveries->isEmpty();

        if ($failedTargets->isEmpty() && ! $isLegacyFailure) {
            return response()->json([
                'success' => false,
                'message' => 'Only failed push deliveries can be retried.',
            ], 422);
        }

        if ($isLegacyFailure) {
            $delivery->update([
                'push_status' => 'pending',
                'push_error' => null,
                'provider_message_id' => null,
                'push_sent_at' => null,
            ]);
            SendPriceAlertNotificationJob::dispatch($delivery->price_alert_event_id);
        } else {
            foreach ($failedTargets as $target) {
                $target->update([
                    'status' => 'pending',
                    'error' => null,
                    'provider_message_id' => null,
                    'sent_at' => null,
                ]);
                SendPriceAlertPushJob::dispatch($target->id);
            }
            $pushDeliveries->aggregate($delivery->id);
        }

        return $this->respond($presenter->present(
            $delivery->fresh(['event.alert.instrument', 'event.alert.user', 'pushDeliveries.pushDevice']),
        ));
    }
}
