<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyUser
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user() || ! $request->user()->is_verified) {
            return response()->json(['message' => 'User is not verified.'], 403);
        }

        return $next($request);
    }
}
