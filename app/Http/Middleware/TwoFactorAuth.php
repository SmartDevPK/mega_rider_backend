<?php
// app/Http/Middleware/TwoFactorAuth.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TwoFactorAuth
{
    public function handle(Request $request, Closure $next)
    {
        // Skip 2FA check for testing/debug
        if (app()->environment('local') && !config('app.enable_2fa_in_local', false)) {
            return $next($request);
        }
        
        if (auth()->check() && auth()->user()->two_factor_enabled && !session('2fa_verified')) {
            return response()->json([
                'success' => false,
                'message' => 'Two-factor authentication required.',
                'requires_2fa' => true,
                'code' => '2FA_REQUIRED'
            ], 403);
        }
        
        return $next($request);
    }
}