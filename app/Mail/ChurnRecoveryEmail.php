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

    public string $unsubscribeUrl;

    public string $oneClickUnsubscribeUrl;

    public string $ctaUrl;

    public function __construct(
        public User $user,
        public int $emailNumber,
        public ?string $lastReadingPassage = null
    ) {
        $this->assertValidEmailNumber();

        $this->unsubscribeUrl = URL::signedRoute(
            'marketing.unsubscribe',
            ['user' => $user],
            now()->addDays(365)
        );

        $this->oneClickUnsubscribeUrl = URL::signedRoute(
            'marketing.unsubscribe.one-click',
            ['user' => $user],
            now()->addDays(365)
        );

        $this->ctaUrl = route('logs.create');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: "emails.churn-recovery-{$this->emailNumber}",
            with: [
                'unsubscribeUrl' => $this->unsubscribeUrl,
                'ctaUrl' => $this->ctaUrl,
            ],
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'List-Unsubscribe' => "<{$this->oneClickUnsubscribeUrl}>",
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ],
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    private function assertValidEmailNumber(): void
    {
        if ($this->emailNumber < 1 || $this->emailNumber > 3) {
            throw new InvalidArgumentException('emailNumber must be between 1 and 3');
        }
    }

    private function emailSubject(): string
    {
        return [
            1 => 'Keep your reading history up to date',
            2 => 'A quick check-in from Delight',
            3 => 'Add your latest reading to Delight',
        ][$this->emailNumber];
    }
}
