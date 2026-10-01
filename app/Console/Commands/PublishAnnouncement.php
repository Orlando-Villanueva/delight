<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Services\AnnouncementEmailLinkValidator;
use App\Services\AnnouncementService;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublishAnnouncement extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'announcements:publish
        {draft : Current draft slug}
        {--dry-run : Report publication state and timing without saving}
        {--yes : Confirm publication without an interactive prompt}
        {--json : Return machine-readable JSON; publication requires --yes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish or schedule an announcement draft in-app';

    /**
     * Execute the console command.
     */
    public function handle(
        AnnouncementService $announcementService,
        AnnouncementEmailLinkValidator $linkValidator,
    ): int {
        $announcement = Announcement::query()
            ->where('slug', Str::slug((string) $this->argument('draft')))
            ->first();

        if (! $announcement || ! $announcement->is_draft) {
            return $this->renderFailure(['draft' => ['Only an existing draft announcement can be published.']]);
        }

        try {
            $linkValidator->validate($announcement);
        } catch (ValidationException $exception) {
            return $this->renderFailure($exception->errors());
        }

        $startsAt = $this->publicationTime($announcement);

        if ($announcement->ends_at?->lte($startsAt)) {
            return $this->renderFailure(['ends_at' => ['The expiry time must be after the publication time.']]);
        }

        $summary = [
            'id' => $announcement->id,
            'slug' => $announcement->slug,
            'title' => $announcement->title,
            'state' => $startsAt->isFuture() ? 'scheduled' : 'published',
            'publication_url' => route('announcements.show', ['slug' => $announcement->slug]),
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $announcement->ends_at?->toIso8601String(),
            'dry_run' => (bool) $this->option('dry-run'),
        ];

        if ($this->option('dry-run')) {
            $this->renderSummary($summary);

            return self::SUCCESS;
        }

        if (! $this->option('json')) {
            $this->renderSummary($summary);
        }

        if (! $this->option('yes')) {
            if ($this->option('json') || ! $this->input->isInteractive()) {
                return $this->renderFailure(['confirmation' => ['Publication requires --yes when running without interactive confirmation.']]);
            }

            if (! $this->confirm('Publish or schedule this announcement in-app?')) {
                $this->info('Publication cancelled.');

                return self::FAILURE;
            }
        }

        $startsAt = $this->publicationTime($announcement);
        $announcementService->publishDraft($announcement, $startsAt);
        $summary['starts_at'] = $startsAt->toIso8601String();
        $summary['state'] = $startsAt->isFuture() ? 'scheduled' : 'published';

        if ($this->option('json')) {
            $this->renderSummary($summary);
        } else {
            $this->info("Announcement {$summary['state']}.");
        }

        return self::SUCCESS;
    }

    private function publicationTime(Announcement $announcement): CarbonInterface
    {
        return $announcement->starts_at?->isFuture() ? $announcement->starts_at : now();
    }

    /** @param array<string, mixed> $summary */
    private function renderSummary(array $summary): void
    {
        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            return;
        }

        $this->table(['Field', 'Value'], collect($summary)
            ->map(function (mixed $value, string $key): array {
                if (is_bool($value)) {
                    $value = $value ? 'Yes' : 'No';
                }

                return [$key, $value ?? 'None'];
            })
            ->values()->all());
    }

    /** @param array<string, array<int, string>> $errors */
    private function renderFailure(array $errors): int
    {
        if ($this->option('json')) {
            $this->line(json_encode(['errors' => $errors], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($errors as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }
        }

        return self::FAILURE;
    }
}
