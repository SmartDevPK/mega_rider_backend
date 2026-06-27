<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckAccountNotDeleted
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        
        if ($user && method_exists($user, 'isDeleted') && $user->isDeleted()) {
            return response()->json([
                'success' => false,
                'message' => 'Account has been deleted. Please contact support.'
            ], 403);
        }
        
        return $next($request);
    }
}