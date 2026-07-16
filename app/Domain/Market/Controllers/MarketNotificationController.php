<?php

namespace App\Domain\Market\Controllers;

use App\Domain\Market\Application\Presenters\MarketNotificationPresenter;
use App\Domain\Shared\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class MarketNotificationController extends Controller
{
    use RespondsWithApi;

    public function index(Request $request, MarketNotificationPresenter $presenter): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->input('per_page', 25)));
        $notifications = $request->user()->notifications()->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $notifications->getCollection()
                ->map(fn (DatabaseNotification $notification): array => $presenter->present($notification))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'last_page' => $notifications->lastPage(),
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    public function markRead(Request $request, string $notification, MarketNotificationPresenter $presenter): JsonResponse
    {
        /** @var DatabaseNotification $item */
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        return $this->respond($presenter->present($item->fresh()));
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updated = $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->respond(['updated' => $updated]);
    }
}
