<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ReadingLog;
use App\Services\BibleReferenceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class ReadingLogGroupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Collection<int, ReadingLog> $logs */
        $logs = $this->resource->all_logs ?? collect([$this->resource]);
        $firstLog = $logs->first();
        $startChapter = (int) $logs->min('chapter');
        $endChapter = (int) $logs->max('chapter');
        $bookName = app(BibleReferenceService::class)->getLocalizedBookName(
            $firstLog->book_id,
            includeDeuterocanonical: true
        );

        return [
            'log_ids' => $logs->pluck('id')->map(fn ($id): int => (int) $id)->values(),
            'book' => [
                'id' => (int) $firstLog->book_id,
                'name' => $bookName,
            ],
            'start_chapter' => $startChapter,
            'end_chapter' => $endChapter === $startChapter ? null : $endChapter,
            'passage' => $this->resource->display_passage_text ?? $firstLog->passage_text,
            'notes_text' => $firstLog->notes_text,
            'date_read' => $firstLog->date_read->toDateString(),
            'logged_at' => $firstLog->created_at->toISOString(),
        ];
    }
}
