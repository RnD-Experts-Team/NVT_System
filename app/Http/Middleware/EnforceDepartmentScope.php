<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the authenticated user can only access data for their own department.
 * Admins (role: admin) are exempt and can access any department.
 *
 * Reads 'department_id' from either the request body or query string.
 */
class EnforceDepartmentScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Admins can access any department
        if ($user && $user->hasRole('admin')) {
            return $next($request);
        }

        $requestedDeptId = (int) $request->input('department_id');

        if ($requestedDeptId && $user && (int) $user->department_id !== $requestedDeptId) {
            return response()->json([
                'message' => 'You are not authorized to access data for this department.',
            ], 403);
        }

        return $next($request);
    }
}
