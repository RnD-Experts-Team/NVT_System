<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsCompliance
{
    /**
     * Allow access only to users who have the Compliance Spatie role.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $user->hasRole('Compliance')) {
            return response()->json(['message' => 'Forbidden. Compliance role required.'], 403);
        }

        return $next($request);
    }
}
