<?php
// app/Notifications/PasswordResetCode.php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetCode extends Notification
{
    use Queueable;
    
    protected $code;
    
    public function __construct($code)
    {
        $this->code = $code;
    }
    
    public function via($notifiable)
    {
        return ['mail'];
    }
    
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Password Reset Code')
            ->greeting('Hello!')
            ->line('You requested to reset your password.')
            ->line('Your password reset code is:')
            ->line('**' . $this->code . '**')
            ->action('Reset Password', url('/reset-password'))
            ->line('This code will expire in 30 minutes.')
            ->line('If you did not request a password reset, no further action is required.');
    }
}