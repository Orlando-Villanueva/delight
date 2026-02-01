<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChurnRecoveryEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public int $emailNumber
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subjects = [
            1 => 'Your Bible reading journey is waiting',
            2 => 'No guilt, just grace - start fresh today',
            3 => 'Should we keep your account?',
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
