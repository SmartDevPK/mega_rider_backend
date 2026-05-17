<?php

namespace App\Services\Rider;

use App\Models\Rider;

class EmailVerificationService
{
    public function verifyRiderEmail($id)
    {
        $rider = Rider::find($id);

        if (!$rider) {
            return response()->json([
                'message' => 'Invalid verification link.'
            ], 404);
        }

        if ($rider->email_verified_at) {
            return response()->json([
                'message' => 'Email already verified.'
            ]);
        }

        $rider->update([
            'email_verified_at' => now()
        ]);

        return response()->json([
            'message' => 'Email verified successfully.'
        ]);
    }
}
