<?php

namespace App\Mail\Customer;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
  use Queueable, SerializesModels;

  public object $user;
  public string $code;

  public function __construct(object $user, string $code)
  {
    $this->user = $user;
    $this->code = $code;
  }

  public function envelope(): Envelope
  {
    return new Envelope(
      subject: 'Password Reset Code - Mega Delivery',
    );
  }

  public function content(): Content
  {
    return new Content(
      view: 'emails.password-reset',
      with: [
        'name' => $this->user->first_name,
        'code' => $this->code,
        'expires_in' => 30,
      ]
    );
  }
}
