<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsManager
{
    /**
     * Allow access only to users who have a manager-level Spatie role
     * (L2, L2PM, L3, L4, L5, L6) AND have a department_id assigned.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $managerRoles = ['L2', 'L2PM', 'L3', 'L4', 'L5', 'L6'];

        if (! $user->hasAnyRole($managerRoles)) {
            return response()->json(['message' => 'Forbidden. Manager role required.'], 403);
        }

        if (! $user->department_id) {
            return response()->json(['message' => 'Forbidden. No department assigned.'], 403);
        }

        return $next($request);
    }
}
