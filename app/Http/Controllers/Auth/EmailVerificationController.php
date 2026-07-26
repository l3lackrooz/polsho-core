<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthUserPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function verify(
        Request $request,
        int $id,
        string $hash,
        AuthUserPresenter $presenter,
    ): JsonResponse|View {
        $user = User::query()->findOrFail($id);

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        $alreadyVerified = $user->hasVerifiedEmail();
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        if (! $request->expectsJson()) {
            return view('auth.email-verification-result', [
                'email' => $user->email,
                'alreadyVerified' => $alreadyVerified,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => $alreadyVerified
                ? 'Email address is already verified.'
                : 'Email address verified.',
            'data' => ['user' => $presenter->present($user->refresh())],
        ]);
    }

    public function resend(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email address is already verified.',
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'Verification email sent.',
        ], 202);
    }
}
