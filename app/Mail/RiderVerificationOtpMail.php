<?php

namespace App\Mail;

use App\Models\Rider;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RiderVerificationOTP extends Mailable
{
    use Queueable, SerializesModels;

    public Rider $rider;
    public string $otp;

    /**
     * Create a new message instance.
     */
    public function __construct(Rider $rider, string $otp)
    {
        $this->rider = $rider;
        $this->otp = $otp;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Verification Code - ' . config('app.name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.rider-verification-otp',
            with: [
                'firstName' => $this->rider->first_name,
                'lastName' => $this->rider->last_name,
                'otp' => $this->otp,
                'email' => $this->rider->email
            ]
        );
    }
}