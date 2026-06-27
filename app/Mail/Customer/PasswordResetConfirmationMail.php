<?php

namespace App\Mail\Customer;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetConfirmationMail extends Mailable
{
  use Queueable, SerializesModels;

  public object $user;

  public function __construct(object $user)
  {
    $this->user = $user;
  }

  public function envelope(): Envelope
  {
    return new Envelope(
      subject: 'Password Changed Successfully - Mega Delivery',
    );
  }

  public function content(): Content
  {
    return new Content(
      view: 'emails.password-reset-confirmation',
      with: [
        'name' => $this->user->first_name,
        'support_email' => 'support@megadelivery.com',
      ]
    );
  }
}
