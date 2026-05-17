<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class RiderApprovedInvitation extends Notification
{
    use Queueable;

    protected $rider;

    public function __construct($rider)
    {
        $this->rider = $rider;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        // Generate a signed URL that allows the rider to set a password
        $url = url("/set-password/{$this->rider->id}?token=" . sha1($this->rider->email));

        return (new MailMessage)
            ->subject('Your rider account has been approved')
            ->line('Admin has approved your registration. Please click the link below to set your password.')
            ->action('Set Password', $url);
    }
}