<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * Sanctum authentication alone is not enough for operational endpoints.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Administrator access is required.',
            ], 403);
        }

        return $next($request);
    }
}
