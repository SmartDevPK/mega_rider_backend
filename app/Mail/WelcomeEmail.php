<?php
// App/Mail/WelcomeEmail.php

namespace App\Mail;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeEmail extends Mailable
{
  use Queueable, SerializesModels;

  public Customer $user;

  public function __construct(Customer $user)
  {
    $this->user = $user;
  }

  public function envelope(): Envelope
  {
    return new Envelope(
      subject: 'Welcome to Mega Rider! 🎉',
    );
  }

  public function content(): Content
  {
    return new Content(
      view: 'emails.welcome',
      with: [
        'name' => $this->user->first_name . ' ' . $this->user->last_name,
        'email' => $this->user->email,
        'referralCode' => $this->user->referral_code,
        'dashboardUrl' => config('app.frontend_url') . '/dashboard',
      ]
    );
  }
}
