<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class AuthController extends Controller
{
    public function checkEmail(Request $request)
    {
        // Validate the email input
        $request->validate([
            'email' => 'required|email'
        ]);

        $email = $request->email;

        // Check if the email exists in the database
        $user = User::where('email', $email)->first();

        if ($user) {
            // Email exists → tell mobile app to go to login
            return response()->json([
                'status' => 'login',
                'message' => 'Email exists. Proceed to login.',
                'data' => [
                    'email' => $user->email,
                    'name' => $user->name
                ]
            ], 200);
        } else {
            // Email not found → tell mobile app to go to register
            return response()->json([
                'status' => 'register',
                'message' => 'Email not found. Proceed to register.',
                'data' => [
                    'email' => $email
                ]
            ], 200);
        }
    }
}
