<?php

namespace App\Services\Rider;

use App\Models\Rider;

class RiderCheckService
{
    public function check(string $email): array
    {
        $email = strtolower(trim($email));
        $rider = Rider::where('email', $email)->first();

        // 1. NOT REGISTERED
        if (!$rider) {
            return $this->response(
                false,
                'NOT_REGISTERED',
                'register',
                'REGISTER',
                'RegisterScreen',
                0,
                'Email not found. Proceed to register.',
                ['email' => $email],
                'START',
                'REGISTER'
            );
        }

        // 2. BANNED ACCOUNT
        if ($rider->is_banned ?? false) {
            return $this->response(
                false,
                'ACCOUNT_BANNED',
                'banned',
                'CONTACT_SUPPORT',
                'SupportScreen',
                0,
                'Your account has been banned. Please contact support.',
                ['rider_id' => $rider->id],
                'BANNED',
                null
            );
        }

        // 3. EMAIL NOT VERIFIED
        if (!$rider->email_verified) {
            return $this->response(
                false,
                'EMAIL_NOT_VERIFIED',
                'unverified',
                'VERIFY_EMAIL',
                'VerifyEmailScreen',
                10,
                'Email exists but is not verified.',
                ['rider_id' => $rider->id, 'email' => $rider->email],
                'EMAIL_VERIFICATION',
                'VERIFY_EMAIL'
            );
        }

        // 4. PROFILE INCOMPLETE
        if (!$rider->profile_completed) {
            return $this->response(
                false,
                'PROFILE_INCOMPLETE',
                'resume',
                'RESUME_REGISTRATION',
                'ProfileCompletionScreen',
                30,
                'Please complete your registration.',
                ['rider_id' => $rider->id],
                'PROFILE',
                'COMPLETE_PROFILE'
            );
        }

        // 5. GUARANTOR STEP
        if (!$rider->guarantors_accepted) {
            return $this->response(
                false,
                'GUARANTOR_PENDING',
                'guarantor_pending',
                'COMPLETE_GUARANTOR',
                'GuarantorScreen',
                60,
                'Please complete your guarantor information.',
                ['rider_id' => $rider->id],
                'GUARANTOR',
                'SUBMIT_GUARANTOR'
            );
        }

        // 6. LICENSE STEP
        if (!$rider->rider_license_accepted) {
            return $this->response(
                false,
                'LICENSE_PENDING',
                'license_pending',
                'UPLOAD_LICENSE',
                'LicenseUploadScreen',
                90,
                'Please upload your rider license.',
                ['rider_id' => $rider->id],
                'LICENSE',
                'UPLOAD_LICENSE'
            );
        }

        // 7. CREATE PASSWORD
        if (!$rider->password_created) {
            return $this->response(
                true,
                'CREATE_PASSWORD',
                'create_password',
                'CREATE_PASSWORD',
                'CreatePasswordScreen',
                95,
                'Account approved. Please create your password.',
                ['rider_id' => $rider->id, 'email' => $rider->email],
                'PASSWORD',
                'CREATE_PASSWORD'
            );
        }

        // 8. FULLY APPROVED
        return $this->response(
            true,
            'APPROVED',
            'login',
            'LOGIN',
            'LoginScreen',
            100,
            'Account fully approved. Proceed to login.',
            ['rider_id' => $rider->id, 'email' => $rider->email],
            'COMPLETED',
            'LOGIN'
        );
    }

    /**
     * Standard response builder
     */
    private function response(
        bool $success,
        string $code,
        string $status,
        string $action,
        string $screen,
        int $progress,
        string $message,
        array $data,
        string $currentStep,
        ?string $nextStep
    ): array {
        return [
            'success' => $success,
            'code' => $code,
            'status' => $status,
            'action' => $action,
            'next_screen' => $screen,
            'progress' => $progress,
            'message' => $message,
            'current_step' => $currentStep, 
            'next_step' => $nextStep,       
            'data' => $data,
        ];
    }
}