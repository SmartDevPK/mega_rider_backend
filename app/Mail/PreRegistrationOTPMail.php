<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PreRegistrationOTPMail extends Mailable
{
  use Queueable, SerializesModels;

  public string $otp;

  public function __construct(string $otp)
  {
    $this->otp = $otp;
  }

  public function envelope(): Envelope
  {
    return new Envelope(
      subject: 'Your OTP Code - Mega Rider Registration',
    );
  }

  public function content(): Content
  {
    return new Content(
      view: 'emails.pre-registration-otp',
      with: [
        'otp' => $this->otp,
        'expires_in' => 3,
      ]
    );
  }
}
