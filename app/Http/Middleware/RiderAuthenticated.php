<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RiderAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->tokenCan('rider:access')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized. Please login first.'
            ], 401);
        }
        
        return $next($request);
    }
}