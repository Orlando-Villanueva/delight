<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreReadingLogRequest;
use App\Http\Requests\Api\V1\UpdateReadingNoteRequest;
use App\Http\Resources\Api\V1\ReadingLogDayResource;
use App\Http\Resources\Api\V1\ReadingLogGroupResource;
use App\Models\ReadingLog;
use App\Services\ReadingLogService;
use App\Services\UserStatisticsService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class ReadingLogController extends Controller
{
    public function __construct(
        private ReadingLogService $readingLogService,
        private UserStatisticsService $userStatisticsService
    ) {}

    public function store(StoreReadingLogRequest $request): JsonResponse
    {
        $data = $request->validated();
        $startChapter = (int) $data['start_chapter'];
        $endChapter = isset($data['end_chapter']) ? (int) $data['end_chapter'] : $startChapter;

        if ($startChapter === $endChapter) {
            $data['chapter'] = $startChapter;
        } else {
            $data['chapters'] = range($startChapter, $endChapter);
        }

        try {
            $result = $this->readingLogService->logReadingWithResult($request->user(), $data);
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'start_chapter' => ['One or more of these chapters have already been logged for this date.'],
            ]);
        }

        $group = $this->readingLogService
            ->getPreparedLogsForDate($request->user(), $data['date_read'], $this->userStatisticsService)
            ?->first(fn ($log): bool => $log->all_logs->contains('id', $result->log->id));

        abort_if($group === null, 500, 'The created reading group could not be loaded.');

        return (new ReadingLogGroupResource($group))
            ->response()
            ->setStatusCode(201);
    }

    public function updateNote(UpdateReadingNoteRequest $request, ReadingLog $readingLog): Response
    {
        $data = $request->validated();
        $this->readingLogService->updateGroupNote(
            $request->user(), $readingLog, $data['log_ids'], $data['notes_text']
        );

        return response()->noContent();
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $logs = $this->readingLogService->getPaginatedDayGroupsFor(
            $request,
            $this->userStatisticsService,
            path: $request->url()
        );
        $logs->setCollection($logs->getCollection()->values());

        return ReadingLogDayResource::collection($logs);
    }
}
