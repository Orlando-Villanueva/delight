<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Services\AnnouncementService;
use App\Services\AnnouncementValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EditAnnouncementDraft extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'announcements:edit
        {draft : Current draft slug}
        {--title= : New announcement title}
        {--slug= : New publication slug}
        {--content-file= : Path to revised Markdown content}
        {--hero-image-path= : New public hero image path}
        {--social-image-path= : New public social image path}
        {--clear-social-image : Remove the social image path}
        {--starts-at= : New proposed publication time}
        {--ends-at= : New expiry time}
        {--clear-ends-at : Remove the expiry time}
        {--json : Return machine-readable JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Edit a persisted announcement draft without publishing or authorizing email delivery';

    /**
     * Execute the console command.
     */
    public function handle(
        AnnouncementService $announcementService,
        AnnouncementValidator $announcementValidator,
    ): int {
        $announcement = Announcement::query()
            ->where('slug', Str::slug((string) $this->argument('draft')))
            ->first();

        if (! $announcement || ! $announcement->is_draft) {
            return $this->renderFailure([
                'draft' => ['Only an existing draft announcement can be edited.'],
            ]);
        }

        if (! $this->hasRequestedChanges()) {
            return $this->renderFailure([
                'changes' => ['At least one draft change option is required.'],
            ]);
        }

        if ($this->option('social-image-path') !== null && $this->option('clear-social-image')) {
            return $this->renderFailure([
                'social_image_path' => ['The social image path cannot be set and cleared together.'],
            ]);
        }

        if ($this->option('ends-at') !== null && $this->option('clear-ends-at')) {
            return $this->renderFailure([
                'ends_at' => ['The expiry time cannot be set and cleared together.'],
            ]);
        }

        try {
            $validated = $announcementValidator->validate(
                $this->draftInput($announcement),
                $announcement,
            );
            $announcement = $announcementService->updateDraft($announcement, $validated);
        } catch (ValidationException $exception) {
            return $this->renderFailure($exception->errors());
        }

        $result = [
            'id' => $announcement->id,
            'slug' => $announcement->slug,
            'state' => 'draft',
            'preview_url' => route('admin.announcements.preview', [
                'announcement' => $announcement->slug,
            ]),
            'publication_url' => route('announcements.show', ['slug' => $announcement->slug]),
            'proposed_starts_at' => $announcement->starts_at?->toIso8601String(),
            'proposed_ends_at' => $announcement->ends_at?->toIso8601String(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Announcement draft updated.');
        $this->table(['Field', 'Value'], collect($result)
            ->map(fn (mixed $value, string $key): array => [$key, $value ?? 'None'])
            ->values()
            ->all());

        return self::SUCCESS;
    }

    private function hasRequestedChanges(): bool
    {
        foreach ([
            'title',
            'slug',
            'content-file',
            'hero-image-path',
            'social-image-path',
            'starts-at',
            'ends-at',
        ] as $option) {
            if ($this->option($option) !== null) {
                return true;
            }
        }

        return (bool) $this->option('clear-social-image')
            || (bool) $this->option('clear-ends-at');
    }

    /**
     * @return array<string, mixed>
     */
    private function draftInput(Announcement $announcement): array
    {
        $input = [
            'title' => $announcement->title,
            'slug' => $announcement->slug,
            'content' => $announcement->content,
            'hero_image_path' => $announcement->hero_image_path,
            'social_image_path' => $announcement->social_image_path,
            'starts_at' => $announcement->starts_at,
            'ends_at' => $announcement->ends_at,
        ];

        foreach ([
            'title' => 'title',
            'slug' => 'slug',
            'hero-image-path' => 'hero_image_path',
            'social-image-path' => 'social_image_path',
            'starts-at' => 'starts_at',
            'ends-at' => 'ends_at',
        ] as $option => $attribute) {
            if ($this->option($option) !== null) {
                $input[$attribute] = $this->option($option);
            }
        }

        if ($this->option('clear-social-image')) {
            $input['social_image_path'] = null;
        }

        if ($this->option('clear-ends-at')) {
            $input['ends_at'] = null;
        }

        $contentFile = $this->option('content-file');

        if ($contentFile !== null) {
            if (! is_string($contentFile) || blank($contentFile) || ! is_file($contentFile) || ! is_readable($contentFile)) {
                throw ValidationException::withMessages([
                    'content' => ['The content file must be an existing readable file.'],
                ]);
            }

            $content = file_get_contents($contentFile);

            if ($content === false) {
                throw ValidationException::withMessages([
                    'content' => ['The content file could not be read.'],
                ]);
            }

            $input['content'] = $content;
        }

        return $input;
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
