<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Rider;
use App\Mail\Customer\PasswordResetMail;
use App\Mail\Customer\PasswordResetConfirmationMail;
use App\Mail\WelcomeEmail;
use App\Mail\VerifyEmailMail;
use App\Mail\EmailVerifiedConfirmationMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class NotificationService
{
    // =========================================================================
    // PASSWORD RESET METHODS
    // =========================================================================

    /**
     * Send password reset code to customer
     */
    public function sendPasswordResetCode(Request $request)
    {
        // Validate the request
        $request->validate([
            'email' => 'required|email'
        ]);

        $email = strtolower(trim($request->email));

        // Find the user
        $user = Customer::where('email', $email)->first();

        // CRITICAL: Check if user exists before proceeding
        if (!$user) {
            Log::warning('Password reset attempted for non-existent email', [
                'email' => $email,
                'ip' => $request->ip()
            ]);

            // For security, return same message even if user doesn't exist
            return back()->with([
                'status' => 'If an account exists with this email, a password reset code has been sent.'
            ]);
        }

        // Generate a 6-digit reset code
        $code = random_int(100000, 999999);

        // Store by email
        Cache::put('password_reset_' . $email, $code, now()->addMinutes(30));

        // Send the reset code
        $sent = $this->sendPasswordResetCodeToUser($user, (string)$code);

        if ($sent) {
            return back()->with('status', 'Password reset code sent successfully!');
        }

        return back()->withErrors(['email' => 'Failed to send reset code. Please try again.']);
    }

    /**
     * Send password reset code to user
     * 
     * @param Customer $user
     * @param string $code
     * @return bool
     */
    public function sendPasswordResetCodeToUser($user, string $code): bool
    {
        // Guard clause - validate user object
        if (!$user || !$user instanceof Customer) {
            Log::error('Invalid user object passed to sendPasswordResetCodeToUser', [
                'user' => $user
            ]);
            return false;
        }

        // Guard clause - ensure user has required properties
        if (!isset($user->email) || !isset($user->id)) {
            Log::error('User object missing required properties', [
                'user_id' => $user->id ?? null,
                'email' => $user->email ?? null
            ]);
            return false;
        }

        try {
            // Send email with reset code (pass both user and code)
            Mail::to($user->email)->send(new PasswordResetMail($user, $code));

            // Log successful sending
            Log::info('Password reset code sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            return true;
        } catch (\Exception $e) {
            // Log the error with details
            Log::error('Failed to send password reset code', [
                'user_id' => $user->id ?? null,
                'email' => $user->email ?? null,
                'error_message' => $e->getMessage(),
                'error_line' => $e->getLine(),
                'error_file' => $e->getFile()
            ]);

            return false;
        }
    }

    /**
     * Send password reset confirmation email
     */
    public function sendPasswordResetConfirmation(Customer $user): bool
    {
        try {
            // Use send() instead of queue() for immediate delivery
            Mail::to($user->email)->send(new \App\Mail\Customer\PasswordResetConfirmationMail($user));
            
            Log::info('Password reset confirmation sent', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send password reset confirmation', [
                'user_id' => $user->id ?? null,
                'email' => $user->email ?? null,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send welcome email to new user
     */
    public function sendWelcomeEmail(Customer|Rider $user): bool
    {
        try {
            Mail::to($user->email)->queue(new WelcomeEmail($user));

            Log::info('Welcome email sent', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send welcome email', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send email verification code
     */
    public function sendVerificationCode(Customer|Rider $user, string $code): bool
    {
        try {
            Mail::to($user->email)->send(new VerifyEmailMail($user, $code));

            Log::info('Verification code sent', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send verification code', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send email verified confirmation
     */
    public function sendEmailVerifiedConfirmation(Customer $user): bool
    {
        try {
            Mail::to($user->email)->send(new EmailVerifiedConfirmationMail($user));

            Log::info('Email verified confirmation sent', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send email verified confirmation', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    // =========================================================================
    // ORDER & DELIVERY METHODS
    // =========================================================================

    /**
     * Send order confirmation notification
     */
    public function sendOrderConfirmation(Customer|Rider $user, array $orderDetails): bool
    {
        try {
            $subject = "Order Confirmation #{$orderDetails['order_id']}";
            $message = "Your order has been confirmed. Total: ₦" . number_format($orderDetails['total'], 2);

            // Send email
            Mail::send('emails.order-confirmation', ['user' => $user, 'order' => $orderDetails], function ($mail) use ($user, $subject) {
                $mail->to($user->email)->subject($subject);
            });

            // Send SMS for important orders
            if (isset($user->phone_number)) {
                $this->sendSms($user->phone_number, $message);
            }

            // Send push notification if FCM token exists
            if (isset($user->fcm_token)) {
                $this->sendPushNotification($user->fcm_token, $subject, $message, $orderDetails);
            }

            Log::info('Order confirmation sent', [
                'user_id' => $user->id,
                'order_id' => $orderDetails['order_id']
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send order confirmation', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send delivery status update
     */
    public function sendDeliveryStatusUpdate(Customer|Rider $user, string $status, array $trackingInfo): bool
    {
        try {
            $subject = "Delivery Status Update: " . ucfirst($status);

            Mail::send('emails.delivery-status', ['user' => $user, 'status' => $status, 'tracking' => $trackingInfo], function ($mail) use ($user, $subject) {
                $mail->to($user->email)->subject($subject);
            });

            // Send SMS for critical updates
            if (in_array($status, ['picked_up', 'out_for_delivery', 'delivered'])) {
                $message = "Your package is {$status}. Track: " . ($trackingInfo['tracking_url'] ?? 'N/A');
                $this->sendSms($user->phone_number, $message);
            }

            Log::info('Delivery status update sent', [
                'user_id' => $user->id,
                'status' => $status
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send delivery status update', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send wallet credit notification
     */
    public function sendWalletCreditNotification(Customer|Rider $user, float $amount, string $reason): bool
    {
        try {
            $subject = "Wallet Credit Alert";
            $message = "₦" . number_format($amount, 2) . " has been added to your wallet. Reason: {$reason}";

            Mail::send('emails.wallet-credit', ['user' => $user, 'amount' => $amount, 'reason' => $reason], function ($mail) use ($user, $subject) {
                $mail->to($user->email)->subject($subject);
            });

            // Send SMS for significant amounts
            if ($amount >= 1000) {
                $this->sendSms($user->phone_number, $message);
            }

            Log::info('Wallet credit notification sent', [
                'user_id' => $user->id,
                'amount' => $amount,
                'reason' => $reason
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send wallet credit notification', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    // =========================================================================
    // BULK NOTIFICATION METHODS
    // =========================================================================

    /**
     * Send bulk notifications to multiple users
     */
    public function sendBulkNotification(array $users, string $title, string $body, string $type = 'email'): array
    {
        $results = [];

        foreach ($users as $user) {
            if ($type === 'email') {
                $results[$user->id] = $this->sendWelcomeEmail($user);
            } elseif ($type === 'sms' && isset($user->phone_number)) {
                $results[$user->id] = $this->sendSms($user->phone_number, $body);
            } elseif ($type === 'push' && isset($user->fcm_token)) {
                $results[$user->id] = $this->sendPushNotification($user->fcm_token, $title, $body);
            }
        }

        return $results;
    }

    // =========================================================================
    // SMS METHODS
    // =========================================================================

    /**
     * Send SMS notification (for phone verification, order updates, etc.)
     */
    public function sendSms(string $phoneNumber, string $message, string $provider = 'default'): bool
    {
        try {
            // Implement SMS provider based on your preference
            switch ($provider) {
                case 'africastalking':
                    return $this->sendAfricaTalkingSms($phoneNumber, $message);
                case 'twilio':
                    return $this->sendTwilioSms($phoneNumber, $message);
                case 'vonage':
                    return $this->sendVonageSms($phoneNumber, $message);
                default:
                    // Log SMS for development
                    Log::info('SMS would be sent', [
                        'phone' => $phoneNumber,
                        'message' => $message
                    ]);
                    return true;
            }
        } catch (\Exception $e) {
            Log::error('Failed to send SMS', [
                'phone' => $phoneNumber,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send SMS via Africa's Talking
     */
    private function sendAfricaTalkingSms(string $phoneNumber, string $message): bool
    {
        $username = config('services.africastalking.username');
        $apiKey = config('services.africastalking.api_key');

        Log::info('Africa\'s Talking SMS would be sent', [
            'phone' => $phoneNumber,
            'message' => $message
        ]);

        return true;
    }

    /**
     * Send SMS via Twilio
     */
    private function sendTwilioSms(string $phoneNumber, string $message): bool
    {
        $accountSid = config('services.twilio.sid');
        $authToken = config('services.twilio.token');
        $fromNumber = config('services.twilio.from');

        Log::info('Twilio SMS would be sent', [
            'phone' => $phoneNumber,
            'message' => $message
        ]);

        return true;
    }

    /**
     * Send SMS via Vonage (formerly Nexmo)
     */
    private function sendVonageSms(string $phoneNumber, string $message): bool
    {
        $apiKey = config('services.vonage.api_key');
        $apiSecret = config('services.vonage.api_secret');

        Log::info('Vonage SMS would be sent', [
            'phone' => $phoneNumber,
            'message' => $message
        ]);

        return true;
    }

    // =========================================================================
    // PUSH NOTIFICATION METHODS
    // =========================================================================

    /**
     * Send push notification via FCM
     */
    public function sendPushNotification(string $fcmToken, string $title, string $body, array $data = []): bool
    {
        try {
            $firebaseUrl = 'https://fcm.googleapis.com/fcm/send';
            $serverKey = config('services.fcm.server_key');

            $payload = [
                'to' => $fcmToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'sound' => 'default',
                ],
                'data' => $data,
                'priority' => 'high',
            ];

            $headers = [
                'Authorization: key=' . $serverKey,
                'Content-Type: application/json',
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $firebaseUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                Log::info('Push notification sent', ['token' => $fcmToken, 'title' => $title]);
                return true;
            }

            Log::error('Push notification failed', [
                'token' => $fcmToken,
                'response' => $response,
                'http_code' => $httpCode
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Failed to send push notification', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Get user type (Customer or Rider)
     *
     * @param object $user
     */
    private function getUserType(object $user): string
    {
        if ($user instanceof Customer) {
            return 'customer';
        }

        if ($user instanceof Rider) {
            return 'rider';
        }

        return 'unknown';
    }
}
