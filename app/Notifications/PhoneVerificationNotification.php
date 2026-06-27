<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\VonageMessage;

class PhoneVerificationNotification extends Notification
{
    use Queueable;

    protected $otpCode;

    public function __construct($otpCode)
    {
        $this->otpCode = $otpCode;
    }

    public function via($notifiable)
    {
        return ['vonage']; // For SMS
        // or return ['twilio']; // if using Twilio
    }

    public function toVonage($notifiable)
    {
        return (new VonageMessage)
            ->content("Your Mega Rider verification code is: {$this->otpCode}. Valid for 15 minutes.")
            ->from('MegaRider');
    }
}