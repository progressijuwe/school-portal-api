<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds an account on the change-password screen until it has one.
 *
 * `must_change_password` was set on every account the admin created but nothing
 * ever read it, so a temporary password — generated once, handed over in
 * person, and often reused across a cohort — stayed valid indefinitely.
 *
 * The frontend renders the same gate from the user payload; this exists so the
 * gate cannot be stepped over by calling the API directly.
 */
class EnsurePasswordChanged
{
    /**
     * Routes that must stay reachable, or the user is locked in a state they
     * cannot act on: read your own identity, set the new password, or leave.
     *
     * @var array<int, string>
     */
    private const ALLOWED = [
        'api/auth/change-password',
        'api/auth/logout',
        'api/profile',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->is(self::ALLOWED)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'You must change your temporary password before continuing.',
            // A distinct code so the client can route to the change-password
            // screen rather than showing this as a generic permission error.
            'code' => 'password_change_required',
        ], 403);
    }
}
