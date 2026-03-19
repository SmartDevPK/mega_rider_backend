<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsFullyVerified
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Not logged in
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }

        // Email not verified
        if (!$user->is_verified) {
            return response()->json([
                'message' => 'Please verify your email before proceeding'
            ], 403);
        }

        // OPTIONAL: Check profile completion
        if (!$user->phoneNumber || !$user->firstname || !$user->lastname) {
            return response()->json([
                'message' => 'Please complete your profile before using this feature'
            ], 403);
        }

        return $next($request);
    }
}

