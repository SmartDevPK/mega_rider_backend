<?php
// app/Http/Controllers/Admin/AdminAuthController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $admin = Admin::where('email', $request->email)
            ->where('is_active', true)
            ->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Update last login
        $admin->update(['last_login_at' => now()]);

        // Delete old tokens
        $admin->tokens()->delete();
        
        // Create new token
        $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'data' => [
                'admin' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'role' => $admin->role,
                ],
                'token' => $token
            ]
        ]);
    }

    public function logout(Request $request)
    {
        // Get user from request (Sanctum)
        $user = $request->user();
        
        if ($user) {
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'status' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Not authenticated'
            ], 401);
        }
        
        return response()->json([
            'status' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ]
        ]);
    }
}