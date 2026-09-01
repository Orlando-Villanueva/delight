<?php

namespace App\Mail;

use App\Models\Announcement;
use App\Models\AnnouncementEmailDelivery;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class AnnouncementEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public string $unsubscribeUrl;

    public string $oneClickUnsubscribeUrl;

    public string $announcementUrl;

    public function __construct(
        public Announcement $announcement,
        public User $user,
        public AnnouncementEmailDelivery $delivery
    ) {
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

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->announcement->title,
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
                'announcementUrl' => $this->announcementUrl,
                'heroImageUrl' => $this->announcement->heroImageUrl(),
                'unsubscribeUrl' => $this->unsubscribeUrl,
            ],
        );
    }

    public function headers(): Headers
    {
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
