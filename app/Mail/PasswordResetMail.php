<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The password reset URL.
     */
    public string $resetUrl;

    private string $emailLocale;

    /**
     * Create a new message instance.
     */
    public function __construct(string $resetUrl, string $locale = 'en')
    {
        $this->resetUrl = $resetUrl;
        $this->emailLocale = $locale;
        $this->locale($locale);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->localized('Reset Your Password - Delight'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset',
            with: [
                'resetUrl' => $this->resetUrl,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    private function localized(string $key): string
    {
        $previousLocale = app()->getLocale();
        app()->setLocale($this->emailLocale);

        try {
            return __($key);
        } finally {
            app()->setLocale($previousLocale);
        }
    }
}
