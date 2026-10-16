<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Someone still on an admin-issued temporary password may only look themselves up, log out and
 * change their password (everything under /api/auth). Everything else answers 403 until they do.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password && ! $request->is('api/auth/*')) {
            return response()->json([
                'message' => 'Please change your temporary password first.',
                'code' => 'password_change_required',
            ], 403);
        }

        return $next($request);
    }
}
