<?php

namespace App\Services;

use App\Mail\AnnouncementEmail;
use App\Models\Announcement;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AnnouncementTestEmailService
{
    public function __construct(private AnnouncementEmailLinkValidator $linkValidator) {}

    public function preview(Announcement $announcement): string
    {
        $announcement->refresh();

        if (! $announcement->is_draft) {
            throw ValidationException::withMessages(['draft' => 'Only an existing draft announcement can be tested.']);
        }

        $this->linkValidator->validate($announcement);
        $recipient = config('mail.admin_address');

        if (Validator::make(['recipient' => $recipient], ['recipient' => ['required', 'string', 'email']])->fails()) {
            throw ValidationException::withMessages(['recipient' => 'Configure a valid ADMIN_EMAIL before sending a test.']);
        }

        return $recipient;
    }

    public function send(Announcement $announcement): string
    {
        $recipient = $this->preview($announcement);
        Mail::to($recipient)->send(AnnouncementEmail::forTest($announcement));

        return $recipient;
    }
}
