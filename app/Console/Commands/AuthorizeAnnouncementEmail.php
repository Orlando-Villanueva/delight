<?php

namespace App\Console\Commands;

use App\Console\CommandOutput;
use App\Models\Announcement;
use App\Services\AnnouncementService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthorizeAnnouncementEmail extends Command
{
    protected $signature = 'announcements:authorize-email
        {announcement : Published or scheduled announcement slug}
        {--dry-run : Preview email authorization and recipient estimates}
        {--yes : Confirm email authorization without an interactive prompt}
        {--json : Return machine-readable JSON; authorization requires --yes}';

    protected $description = 'Authorize email for a published or scheduled announcement';

    public function handle(AnnouncementService $announcementService, CommandOutput $output): int
    {
        $announcement = Announcement::query()
            ->where('slug', Str::slug((string) $this->argument('announcement')))
            ->first();

        if (! $announcement) {
            return $output->renderFailure($this, ['announcement' => ['The announcement does not exist.']]);
        }

        try {
            $summary = $announcementService->previewEmailAuthorization($announcement);
        } catch (ValidationException $exception) {
            return $output->renderFailure($this, $exception->errors());
        }

        $summary['dry_run'] = (bool) $this->option('dry-run');
        $summary['result'] = $announcement->email_broadcast_authorized_at ? 'already_authorized' : 'preview';

        if ($this->option('dry-run') || $announcement->email_broadcast_authorized_at !== null) {
            $output->renderSummary($this, $summary);

            return self::SUCCESS;
        }

        if (! $output->confirmAction($this, $this->input->isInteractive(), $summary, 'Email authorization', 'Authorize email delivery for this announcement?')) {
            return self::FAILURE;
        }

        try {
            $authorized = $announcementService->authorizeEmail($announcement);
        } catch (ValidationException $exception) {
            return $output->renderFailure($this, $exception->errors());
        }

        $summary['result'] = $authorized ? 'authorized' : 'already_authorized';
        $summary['email_broadcast_authorized_at'] = $announcement->email_broadcast_authorized_at?->toIso8601String();

        if ($this->option('json')) {
            $output->renderSummary($this, $summary);
        } else {
            $this->info($authorized ? 'Announcement email authorized.' : 'Announcement email already authorized.');
        }

        return self::SUCCESS;
    }
}
