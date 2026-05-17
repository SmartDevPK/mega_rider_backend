<?php
// app/Mail/RiderApprovedMail.php

namespace App\Mail;

use App\Models\Rider;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RiderApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $rider;
    public $setPasswordUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(Rider $rider)
    {
        $this->rider = $rider;
        // Generate URL for password setup
        $this->setPasswordUrl = url("/rider/set-password?email=" . urlencode($rider->email) . "&token=" . $rider->id);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Congratulations! Your Rider Application Has Been Approved',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.rider-approved',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}