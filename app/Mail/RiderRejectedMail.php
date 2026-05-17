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

    public function __construct(Rider $rider)
    {
        $this->rider = $rider;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Congratulations! Your Rider Application Has Been Approved',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.rider-approved',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}