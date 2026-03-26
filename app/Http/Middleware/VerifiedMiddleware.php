<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifiedMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        if (!$user || !$user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Email verification required.',
                'requires_verification' => true
            ], 403);
        }
        
        return $next($request);
    }
}