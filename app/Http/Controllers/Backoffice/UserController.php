<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * A paginated, password-safe view of application accounts for Backoffice.
     * The route is protected by auth:sanctum and EnsureAdmin.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->input('per_page', 25)));
        $search = trim((string) $request->input('search', ''));

        $users = User::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('is_admin'), fn ($query) => $query->where('is_admin', $request->boolean('is_admin')))
            ->when($request->input('verification') === 'verified', fn ($query) => $query->whereNotNull('email_verified_at'))
            ->when($request->input('verification') === 'unverified', fn ($query) => $query->whereNull('email_verified_at'))
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users->getCollection()->map(fn (User $user): array => $this->present($user))->values()->all(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_admin' => $user->is_admin,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'phone_verified_at' => $user->phone_verified_at?->toISOString(),
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }
}
