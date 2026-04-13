<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Admins do not have a profile page.',
            ], 403);
        }

        return $next($request);
    }
}