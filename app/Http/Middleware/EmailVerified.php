<?php
// app/Http/Middleware/EmailVerified.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EmailVerified
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check() && !auth()->user()->is_verified) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email address to access this resource.'
            ], 403);
        }
        
        return $next($request);
    }
}