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

class ChurnRecoveryEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $unsubscribeUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public int $emailNumber,
        public ?string $lastReadingPassage = null
    ) {
        if ($emailNumber < 1 || $emailNumber > 3) {
            throw new \InvalidArgumentException('emailNumber must be between 1 and 3');
        }

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
        $subjects = [
            1 => 'Your Bible reading journey is waiting',
            2 => 'No guilt, just grace - start fresh today',
            3 => "Always here when you're ready",
        ];

        return new Envelope(
            subject: $subjects[$this->emailNumber] ?? 'A message from Delight',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: "emails.churn-recovery-{$this->emailNumber}",
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
