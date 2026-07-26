<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function requestLink(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email:filter', 'max:255'],
        ]);

        // Always return the same response so this endpoint cannot reveal
        // whether an email address is registered.
        Password::sendResetLink(['email' => $credentials['email']]);

        return response()->json([
            'success' => true,
            'message' => 'If an account exists for this email, a password reset link has been sent.',
        ]);
    }

    public function create(Request $request, string $token): View
    {
        return view('auth.password-reset', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email:filter'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $credentials,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
                $user->tokens()->delete();
                event(new PasswordReset($user));
            },
        );

        return back()->with(
            $status === Password::PASSWORD_RESET ? 'status' : 'error',
            __($status),
        );
    }
}
