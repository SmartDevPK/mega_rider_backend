<?php
// app/Http/Middleware/UserActive.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class UserActive
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check() && !auth()->user()->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated. Please contact support.',
                'code' => 'ACCOUNT_DEACTIVATED'
            ], 403);
        }
        
        return $next($request);
    }
}