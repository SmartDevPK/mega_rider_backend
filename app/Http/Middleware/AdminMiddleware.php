<?php
// app/Http/Middleware/AdminMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Try to get user from Sanctum first
        $user = $request->user();
        
        // If not found, try admin guard
        if (!$user) {
            $user = Auth::guard('admin')->user();
        }
        
        // If still not found, try default guard
        if (!$user) {
            $user = Auth::user();
        }

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized - Please login first'
            ], 401);
        }

        // Check if user is admin
        $isAdmin = false;
        
        // Check by model type
        if ($user instanceof \App\Models\Admin) {
            $isAdmin = true;
        }
        
        // Check by role field
        if (isset($user->role) && $user->role === 'admin') {
            $isAdmin = true;
        }

        if (!$isAdmin) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized - Admin access required',
                'user_type' => get_class($user),
                'user_role' => $user->role ?? 'no role'
            ], 403);
        }

        return $next($request);
    }
}