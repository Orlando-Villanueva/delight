<?php

namespace App\Mail;

use App\Models\User;
use App\Services\BibleReferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
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

    public ?string $lastReadingPassage;

    private string $emailLocale;

    public function __construct(
        public User $user,
        public int $emailNumber,
        ?string $lastReadingPassage = null,
        ?int $lastReadingBookId = null,
        ?int $lastReadingChapter = null,
    ) {
        $this->emailLocale = $user->preferredLocale();
        $this->locale($this->emailLocale);
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
        $this->lastReadingPassage = $this->localizedPassage($lastReadingPassage, $lastReadingBookId, $lastReadingChapter);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.updates_from.address'), config('mail.updates_from.name')),
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
     * @return array<int, Attachment>
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
        return $this->localized([
            1 => 'Keep your reading history up to date',
            2 => 'A quick check-in from Delight',
            3 => 'Add your latest reading to Delight',
        ][$this->emailNumber]);
    }

    private function localized(string $subject): string
    {
        $previousLocale = app()->getLocale();
        app()->setLocale($this->emailLocale);

        try {
            return __($subject);
        } finally {
            app()->setLocale($previousLocale);
        }
    }

    private function localizedPassage(?string $passage, ?int $bookId, ?int $chapter): ?string
    {
        if ($passage === null || $this->emailLocale !== 'fr' || $bookId === null || $chapter === null) {
            return $passage;
        }

        if ($chapter < 1) {
            return $passage;
        }

        $references = app(BibleReferenceService::class);
        $includeDeuterocanonical = $this->user->includesDeuterocanonicalBooks() || $bookId > 66;
        $englishBook = $references->getBibleBook($bookId, 'en', $includeDeuterocanonical)['name'] ?? null;
        $frenchBook = $references->getBibleBook($bookId, 'fr', $includeDeuterocanonical)['name'] ?? null;

        if ($englishBook === null || $frenchBook === null) {
            return $passage;
        }

        return preg_replace('/^'.preg_quote($englishBook, '/').'(?=\s)/u', $frenchBook, $passage) ?? $passage;
    }
}
