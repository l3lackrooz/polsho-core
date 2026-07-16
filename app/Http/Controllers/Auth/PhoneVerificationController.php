<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePhoneRequest;
use App\Http\Requests\VerifyPhoneRequest;
use App\Models\User;
use App\Services\AuthUserPresenter;
use App\Services\PhoneVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhoneVerificationController extends Controller
{
    public function updatePhone(
        UpdatePhoneRequest $request,
        PhoneVerificationService $verification,
        AuthUserPresenter $presenter,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $user = $verification->updatePhone($user, $request->validated('phone'));

        return response()->json([
            'success' => true,
            'data' => ['user' => $presenter->present($user)],
        ]);
    }

    public function sendCode(Request $request, PhoneVerificationService $verification): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $verification->sendCode($user);

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent.',
        ], 202);
    }

    public function verify(
        VerifyPhoneRequest $request,
        PhoneVerificationService $verification,
        AuthUserPresenter $presenter,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $user = $verification->verify($user, $request->validated('code'));

        return response()->json([
            'success' => true,
            'data' => ['user' => $presenter->present($user)],
        ]);
    }
}
