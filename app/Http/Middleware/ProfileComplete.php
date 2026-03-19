<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ProfileComplete
{
    public function handle(Request $request, Closure $next)
    {
        // Check if user profile is complete
        // Add your logic here based on your requirements
        if ($request->user() && !$this->isProfileComplete($request->user())) {
            return response()->json([
                'error' => 'Profile incomplete',
                'message' => 'Please complete your profile first'
            ], 403);
        }
        
        return $next($request);
    }
    
    private function isProfileComplete($user)
    {
        // Define what makes a profile complete
        // Example: check if required fields are filled
        return !empty($user->phone) && !empty($user->address);
    }
}