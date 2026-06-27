<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPasswordExpiry
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        
        if ($user && $user->isPasswordExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Your password has expired. Please update your password.',
                'requires_password_update' => true,
                'password_expired' => true
            ], 403);
        }
        
        return $next($request);
    }
}