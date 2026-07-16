<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\AuthUserPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, AuthUserPresenter $presenter): JsonResponse
    {
        $user = User::query()->create($request->safe()->except(['password_confirmation', 'device_name']));
        $user->sendEmailVerificationNotification();
        $token = $user->createToken($request->input('device_name', 'polsho-mobile'));

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token->plainTextToken,
                'user' => $presenter->present($user),
            ],
        ], 201);
    }

    /**
     * Issue a personal access token for valid credentials.
     */
    public function login(LoginRequest $request, AuthUserPresenter $presenter): JsonResponse
    {
        $user = User::query()->where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if ($request->input('device_name') === 'backoffice-web' && ! $user->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Administrator access is required.',
            ], 403);
        }

        $token = $user->createToken($request->input('device_name', 'backoffice'));

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token->plainTextToken,
                'user' => $presenter->present($user),
            ],
        ]);
    }

    /**
     * Revoke the token used for the current request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out.',
        ]);
    }

    /**
     * The currently authenticated user.
     */
    public function me(Request $request, AuthUserPresenter $presenter): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $presenter->present($request->user()),
            ],
        ]);
    }
}
