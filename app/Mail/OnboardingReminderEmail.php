<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class OnboardingReminderEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $unsubscribeUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(public User $user)
    {
        $this->unsubscribeUrl = URL::signedRoute(
            'marketing.unsubscribe',
            ['user' => $user],
            now()->addDays(365)
        );
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'A gentle reminder from Delight',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.onboarding-reminder',
            with: [
                'unsubscribeUrl' => $this->unsubscribeUrl,
            ],
        );
    }

    /**
     * Get the message headers.
     */
    public function headers(): Headers
    {
        return new Headers(
            text: [
                'List-Unsubscribe' => "<mailto:unsubscribe@delight.io?subject=Unsubscribe>, <{$this->unsubscribeUrl}>",
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
