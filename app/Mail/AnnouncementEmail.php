<?php

namespace App\Mail;

use App\Models\Announcement;
use App\Models\AnnouncementEmailDelivery;
use App\Models\User;
use App\Services\AnnouncementEmailContentRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AnnouncementEmail extends Mailable
{
    use Queueable, SerializesModels;

    public ?string $unsubscribeUrl = null;

    public ?string $oneClickUnsubscribeUrl = null;

    public string $announcementUrl;

    private ?string $testMessageId = null;

    public function __construct(
        public Announcement $announcement,
        public ?User $user,
        public ?AnnouncementEmailDelivery $delivery,
        private AnnouncementEmailContentRenderer $contentRenderer = new AnnouncementEmailContentRenderer,
        public bool $isTest = false,
    ) {
        if ($this->isTest) {
            $this->announcementUrl = route('admin.announcements.preview', $announcement->slug);
            $this->testMessageId = 'announcement-test-'.Str::uuid().'@'.parse_url(config('app.url'), PHP_URL_HOST);

            return;
        }

        if ($user === null || $delivery === null) {
            throw new InvalidArgumentException('Subscriber emails require a user and delivery.');
        }

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

        $this->announcementUrl = route('announcements.show', $announcement->slug);
    }

    public static function forTest(Announcement $announcement): self
    {
        return new self($announcement, null, null, isTest: true);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->isTest ? '[TEST] ' : '').$this->announcement->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.announcement',
            with: [
                'announcementHtml' => $this->contentRenderer->render($this->announcement),
                'announcementUrl' => $this->announcementUrl,
                'heroImageUrl' => $this->announcement->heroImageUrl(),
                'unsubscribeUrl' => $this->unsubscribeUrl,
            ],
        );
    }

    public function headers(): Headers
    {
        if ($this->isTest) {
            return new Headers(messageId: $this->testMessageId);
        }

        return new Headers(
            messageId: $this->delivery->message_id,
            text: [
                'List-Unsubscribe' => "<{$this->oneClickUnsubscribeUrl}>",
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
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
}
