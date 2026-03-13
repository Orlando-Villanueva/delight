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
use InvalidArgumentException;

class ChurnRecoveryEmail extends Mailable
{
    use Queueable, SerializesModels;

    public const SEQUENCE_LEGACY = 'legacy';

    public const SEQUENCE_THIRTY_TO_SIXTY_FOLLOWUP = 'thirty_sixty_followup';

    public string $unsubscribeUrl;

    public string $ctaUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public int $emailNumber,
        public ?string $lastReadingPassage = null,
        public string $sequence = self::SEQUENCE_LEGACY
    ) {
        $this->assertValidEmailNumber();

        $this->unsubscribeUrl = URL::signedRoute(
            'marketing.unsubscribe',
            ['user' => $user],
            now()->addDays(365)
        );

        $this->ctaUrl = $this->sequence === self::SEQUENCE_THIRTY_TO_SIXTY_FOLLOWUP
            ? route('logs.create')
            : route('dashboard');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectForSequence(),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: $this->viewForSequence(),
            with: [
                'unsubscribeUrl' => $this->unsubscribeUrl,
                'ctaUrl' => $this->ctaUrl,
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

    private function assertValidEmailNumber(): void
    {
        $maxEmailNumber = $this->sequence === self::SEQUENCE_THIRTY_TO_SIXTY_FOLLOWUP ? 2 : 3;

        if ($this->emailNumber < 1 || $this->emailNumber > $maxEmailNumber) {
            throw new InvalidArgumentException("emailNumber must be between 1 and {$maxEmailNumber}");
        }
    }

    private function subjectForSequence(): string
    {
        return match ($this->sequence) {
            self::SEQUENCE_THIRTY_TO_SIXTY_FOLLOWUP => [
                1 => 'Restart with one simple reading today',
                2 => 'Take 60 seconds to get back into the habit',
            ][$this->emailNumber],
            default => [
                1 => 'Your Bible reading journey is waiting',
                2 => 'No guilt, just grace - start fresh today',
                3 => "Always here when you're ready",
            ][$this->emailNumber] ?? 'A message from Delight',
        };
    }

    private function viewForSequence(): string
    {
        return match ($this->sequence) {
            self::SEQUENCE_THIRTY_TO_SIXTY_FOLLOWUP => "emails.churn-recovery-30-60-{$this->emailNumber}",
            default => "emails.churn-recovery-{$this->emailNumber}",
        };
    }
}
