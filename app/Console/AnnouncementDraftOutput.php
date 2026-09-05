<?php

namespace App\Console;

use App\Models\Announcement;
use Illuminate\Console\Command;

class AnnouncementDraftOutput
{
    public function render(Command $command, Announcement $announcement, string $message): int
    {
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

        if ($command->option('json')) {
            $command->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        }

        $command->info($message);
        $command->table(['Field', 'Value'], collect($result)
            ->map(fn (mixed $value, string $key): array => [$key, $value ?? 'None'])
            ->values()
            ->all());

        return Command::SUCCESS;
    }
}
