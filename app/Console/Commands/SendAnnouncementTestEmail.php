<?php

namespace App\Console\Commands;

use App\Mail\AnnouncementEmail;
use App\Models\Announcement;
use App\Services\AnnouncementEmailLinkValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SendAnnouncementTestEmail extends Command
{
    protected $signature = 'announcements:test-email
        {draft : Current draft slug}
        {--dry-run : Show the test recipient without sending}
        {--yes : Confirm the test send without an interactive prompt}';

    protected $description = 'Send an optional announcement draft test email only to the configured admin inbox';

    public function handle(AnnouncementEmailLinkValidator $linkValidator): int
    {
        $announcement = Announcement::query()
            ->where('slug', Str::slug((string) $this->argument('draft')))
            ->first();

        if (! $announcement || ! $announcement->is_draft) {
            $this->error('Only an existing draft announcement can be tested.');

            return self::FAILURE;
        }

        try {
            $linkValidator->validate($announcement);
        } catch (ValidationException $exception) {
            foreach ($exception->errors()['content'] as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $recipient = config('mail.admin_address');

        if (Validator::make(['recipient' => $recipient], ['recipient' => ['required', 'string', 'email']])->fails()) {
            $this->error('Configure a valid ADMIN_EMAIL before sending a test.');

            return self::FAILURE;
        }

        $this->line("Draft: {$announcement->title}");
        $this->line("Test recipient: {$recipient}");

        if ($this->option('dry-run')) {
            $this->info('Dry run. No email sent.');

            return self::SUCCESS;
        }

        if (! $this->option('yes')) {
            if (! $this->input->isInteractive()) {
                $this->error('Test sending requires --yes when running without interactive confirmation.');

                return self::FAILURE;
            }

            if (! $this->confirm('Send one test email to this admin inbox?')) {
                $this->info('Test send cancelled.');

                return self::FAILURE;
            }
        }

        try {
            Mail::to($recipient)->send(AnnouncementEmail::forTest($announcement));
        } catch (TransportExceptionInterface $exception) {
            report($exception);
            $this->error('The mail transport rejected the test email. Check the application logs for details.');

            return self::FAILURE;
        }

        $this->info('Test email submitted to the mail transport. Check your inbox to confirm delivery.');

        return self::SUCCESS;
    }
}
