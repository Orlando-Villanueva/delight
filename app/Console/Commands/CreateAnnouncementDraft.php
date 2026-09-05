<?php

namespace App\Console\Commands;

use App\Console\AnnouncementDraftOutput;
use App\Services\AnnouncementService;
use App\Services\AnnouncementValidator;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class CreateAnnouncementDraft extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'announcements:draft
        {--title= : Announcement title}
        {--slug= : Optional publication slug; defaults to the title slug}
        {--content-file= : Path to a Markdown content file}
        {--hero-image-path= : Public hero image path}
        {--social-image-path= : Optional public social image path}
        {--starts-at= : Proposed publication time; defaults to now}
        {--ends-at= : Optional expiry time}
        {--json : Return machine-readable JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a persisted announcement draft without publishing or authorizing email delivery';

    /**
     * Execute the console command.
     */
    public function handle(
        AnnouncementService $announcementService,
        AnnouncementValidator $announcementValidator,
        AnnouncementDraftOutput $draftOutput,
    ): int {
        $contentFile = $this->option('content-file');

        if (! is_string($contentFile) || blank($contentFile)) {
            return $this->renderFailure(['content' => ['The content file option is required.']]);
        }

        if (! is_file($contentFile) || ! is_readable($contentFile)) {
            return $this->renderFailure(['content' => ['The content file must be an existing readable file.']]);
        }

        $content = file_get_contents($contentFile);

        if ($content === false) {
            return $this->renderFailure(['content' => ['The content file could not be read.']]);
        }

        try {
            $validated = $announcementValidator->validate([
                'title' => $this->option('title'),
                'slug' => $this->option('slug'),
                'content' => $content,
                'hero_image_path' => $this->option('hero-image-path'),
                'social_image_path' => $this->option('social-image-path'),
                'starts_at' => $this->option('starts-at'),
                'ends_at' => $this->option('ends-at'),
            ]);
            $announcement = $announcementService->createDraft($validated);
        } catch (ValidationException $exception) {
            return $this->renderFailure($exception->errors());
        }

        return $draftOutput->render($this, $announcement, 'Announcement draft created.');
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    private function renderFailure(array $errors): int
    {
        if ($this->option('json')) {
            $this->line(json_encode(['errors' => $errors], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            return self::FAILURE;
        }

        foreach ($errors as $messages) {
            foreach ($messages as $message) {
                $this->error($message);
            }
        }

        return self::FAILURE;
    }
}
