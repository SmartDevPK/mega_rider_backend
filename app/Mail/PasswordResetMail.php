<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user
    ) {}

    public function build(): self
    {
        return $this->subject('Reset Your Password')
                    ->markdown('emails.password-reset')
                    ->with([
                        'name' => $this->user->firstname,
                        'code' => $this->user->password_reset_code,
                    ]);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.password-reset',
            with: [
                'name' => $this->user->firstname,
                'code' => $this->user->password_reset_code,
            ]
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}