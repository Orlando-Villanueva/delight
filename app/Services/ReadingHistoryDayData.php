<?php

namespace App\Services;

use App\Models\ReadingLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/** @phpstan-type HistoryGroup array{id: int, passage: string, notes: ?string, chapterCount: int, loggedAt: string, timeLabel: string, chapters: array<int, array{id: int, chapter: int}>, noteUrl: string, deleteUrl: string, deleteChaptersUrl: string} */
final class ReadingHistoryDayData
{
    /**
     * @param  Collection<int, ReadingLog>  $displayLogs
     * @return array{date: string, label: string, groups: array<int, array{id: int, passage: string, notes: ?string, chapterCount: int, loggedAt: string, timeLabel: string, chapters: array<int, array{id: int, chapter: int}>, noteUrl: string, deleteUrl: string, deleteChaptersUrl: string}>}
     */
    public static function fromDisplayLogs(string $date, Collection $displayLogs): array
    {
        $day = Carbon::parse($date)->locale(app()->getLocale());
        $groups = $displayLogs->map(function (ReadingLog $log): array {
            /** @var Collection<int, ReadingLog> $chapters */
            $chapters = $log->all_logs ?? collect([$log]);

            return [
                'id' => (int) $log->id,
                'passage' => (string) ($log->display_passage_text ?? $log->passage_text),
                'notes' => $log->notes_text,
                'chapterCount' => (int) ($log->chapters_count ?? $chapters->count()),
                'loggedAt' => $log->created_at->toISOString(),
                'timeLabel' => $log->created_at->format('g:i A'),
                'chapters' => $chapters->map(fn (ReadingLog $chapter): array => [
                    'id' => (int) $chapter->id,
                    'chapter' => (int) $chapter->chapter,
                ])->values()->all(),
                'noteUrl' => route('logs.notes.update', $log),
                'deleteUrl' => route('logs.destroy', $log),
                'deleteChaptersUrl' => route('logs.chapters.destroy', $log),
            ];
        })->values()->all();

        return [
            'date' => $date,
            'label' => $day->format('M j, Y'),
            'groups' => $groups,
        ];
    }
}
