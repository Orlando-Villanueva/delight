<?php

use App\Enums\OnboardingStep;
use App\Models\BookProgress;
use App\Models\ChurnRecoveryCampaign;
use App\Models\ReadingLog;
use App\Models\User;
use Carbon\Carbon;

const MOBILE_READING_LOGS_ENDPOINT = '/api/v1/reading-logs';

function mobileReadingToken(User $user, array $abilities = ['mobile']): string
{
    return $user->createToken('Mobile API test', $abilities)->plainTextToken;
}

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-08-08 12:00:00'));
});

it('requires a mobile token ability for creation and history', function () {
    $user = User::factory()->create();

    $this->postJson(MOBILE_READING_LOGS_ENDPOINT, [])->assertUnauthorized();
    $this->getJson(MOBILE_READING_LOGS_ENDPOINT)->assertUnauthorized();

    $token = mobileReadingToken($user, ['reporting']);

    $this->withToken($token)->postJson(MOBILE_READING_LOGS_ENDPOINT, [])->assertForbidden();
    $this->withToken($token)->getJson(MOBILE_READING_LOGS_ENDPOINT)->assertForbidden();
});

it('creates a single chapter and returns its canonical group', function () {
    $user = User::factory()->create();

    $response = $this->withToken(mobileReadingToken($user))->postJson(MOBILE_READING_LOGS_ENDPOINT, [
        'book_id' => 43,
        'start_chapter' => 3,
        'end_chapter' => null,
        'date_read' => today()->toDateString(),
        'notes_text' => 'Born of the Spirit.',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.book.id', 43)
        ->assertJsonPath('data.book.name', 'John')
        ->assertJsonPath('data.start_chapter', 3)
        ->assertJsonPath('data.end_chapter', null)
        ->assertJsonPath('data.passage', 'John 3')
        ->assertJsonPath('data.notes_text', 'Born of the Spirit.')
        ->assertJsonPath('data.date_read', today()->toDateString())
        ->assertJsonCount(1, 'data.log_ids')
        ->assertJsonStructure(['data' => ['logged_at']]);

    expect($user->readingLogs()
        ->where('book_id', 43)
        ->where('chapter', 3)
        ->whereDate('date_read', today())
        ->exists())->toBeTrue();
});

it('creates a chapter range atomically with book progress', function () {
    $user = User::factory()->create();

    $this->withToken(mobileReadingToken($user))->postJson(MOBILE_READING_LOGS_ENDPOINT, [
        'book_id' => 43,
        'start_chapter' => 1,
        'end_chapter' => 3,
        'date_read' => today()->subDay()->toDateString(),
        'notes_text' => null,
    ])
        ->assertCreated()
        ->assertJsonPath('data.start_chapter', 1)
        ->assertJsonPath('data.end_chapter', 3)
        ->assertJsonPath('data.passage', 'John 1-3')
        ->assertJsonCount(3, 'data.log_ids');

    expect($user->readingLogs()->orderBy('chapter')->pluck('chapter')->all())->toBe([1, 2, 3])
        ->and($user->bookProgress()->where('book_id', 43)->firstOrFail()->chapters_read)->toBe([1, 2, 3]);
});

it('returns field-specific validation errors', function (array $overrides, string $field) {
    $user = User::factory()->create();
    $payload = [
        'book_id' => 43,
        'start_chapter' => 1,
        'end_chapter' => null,
        'date_read' => today()->toDateString(),
        'notes_text' => null,
    ];

    $this->withToken(mobileReadingToken($user))
        ->postJson(MOBILE_READING_LOGS_ENDPOINT, array_replace($payload, $overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'unknown book' => [['book_id' => 999], 'book_id'],
    'book outside Protestant canon' => [['book_id' => 67], 'book_id'],
    'invalid start chapter' => [['start_chapter' => 22], 'start_chapter'],
    'invalid end chapter' => [['end_chapter' => 22], 'end_chapter'],
    'inverted range' => [['start_chapter' => 3, 'end_chapter' => 2], 'end_chapter'],
    'date older than yesterday' => [['date_read' => '2026-08-06'], 'date_read'],
    'date with a non-canonical format' => [['date_read' => today()->format('m/d/Y')], 'date_read'],
    'notes over one thousand characters' => [['notes_text' => str_repeat('a', 1001)], 'notes_text'],
]);

it('allows a deuterocanonical book only when enabled for the user', function () {
    $user = User::factory()->create([
        'deuterocanonical_books_enabled_at' => now(),
    ]);

    $this->withToken(mobileReadingToken($user))->postJson(MOBILE_READING_LOGS_ENDPOINT, [
        'book_id' => 67,
        'start_chapter' => 1,
        'date_read' => today()->toDateString(),
    ])->assertCreated();
});

it('rolls back earlier logs and progress when a range fails partway through', function () {
    $user = User::factory()->create([
        'celebrated_first_reading_at' => now()->subDay(),
    ]);
    ReadingLog::factory()->for($user)->create([
        'book_id' => 43,
        'chapter' => 2,
        'date_read' => today()->toDateString(),
    ]);

    $this->withToken(mobileReadingToken($user))->postJson(MOBILE_READING_LOGS_ENDPOINT, [
        'book_id' => 43,
        'start_chapter' => 1,
        'end_chapter' => 2,
        'date_read' => today()->toDateString(),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('start_chapter');

    expect($user->readingLogs()
        ->where('book_id', 43)
        ->where('chapter', 1)
        ->whereDate('date_read', today())
        ->exists())->toBeFalse();
    $this->assertDatabaseMissing('book_progress', [
        'user_id' => $user->id,
        'book_id' => 43,
    ]);
});

it('returns only the users history newest first grouped by date session and contiguous chapters', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $sessionTime = now()->subHour();

    foreach ([1, 2, 4] as $chapter) {
        ReadingLog::factory()->for($user)->create([
            'book_id' => 43,
            'chapter' => $chapter,
            'date_read' => today()->toDateString(),
            'created_at' => $sessionTime,
            'updated_at' => $sessionTime,
        ]);
    }

    foreach (range(1, 8) as $offset) {
        ReadingLog::factory()->for($user)->create([
            'book_id' => 43,
            'chapter' => $offset + 10,
            'date_read' => today()->subDays($offset)->toDateString(),
            'created_at' => $sessionTime->copy()->subDays($offset),
            'updated_at' => $sessionTime->copy()->subDays($offset),
        ]);
    }

    $otherLog = ReadingLog::factory()->for($otherUser)->create([
        'book_id' => 43,
        'chapter' => 10,
        'date_read' => today()->toDateString(),
    ]);

    $pageOne = $this->withToken(mobileReadingToken($user))->getJson(MOBILE_READING_LOGS_ENDPOINT.'?page=1');

    $pageOne
        ->assertSuccessful()
        ->assertJsonCount(8, 'data')
        ->assertJsonPath('data.0.date_read', today()->toDateString())
        ->assertJsonPath('data.0.groups.0.start_chapter', 1)
        ->assertJsonPath('data.0.groups.0.end_chapter', 2)
        ->assertJsonPath('data.0.groups.0.passage', 'John 1-2')
        ->assertJsonPath('data.0.groups.1.start_chapter', 4)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.per_page', 8)
        ->assertJsonPath('meta.total', 9);

    expect(collect($pageOne->json('data'))->pluck('groups')->flatten(1)->pluck('log_ids')->flatten())
        ->not->toContain($otherLog->id);

    $this->withToken(mobileReadingToken($user))->getJson(MOBILE_READING_LOGS_ENDPOINT.'?page=2')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.date_read', today()->subDays(8)->toDateString());
});

it('keeps web and api domain side effects in parity', function () {
    $webUser = User::factory()->create();
    $apiUser = User::factory()->create();
    $campaignState = [
        'started_at' => now()->subDay(),
        'observed_until' => now()->addDays(6),
    ];
    $webCampaign = ChurnRecoveryCampaign::factory()->for($webUser)->create($campaignState);
    $apiCampaign = ChurnRecoveryCampaign::factory()->for($apiUser)->create($campaignState);
    $payload = [
        'book_id' => 43,
        'start_chapter' => 1,
        'date_read' => today()->toDateString(),
        'notes_text' => 'Shared domain path.',
    ];

    $this->withToken(mobileReadingToken($apiUser))->postJson(MOBILE_READING_LOGS_ENDPOINT, $payload)->assertCreated();
    $this->actingAs($webUser)->post(route('logs.store'), $payload)->assertSuccessful();

    $domainState = function (User $user, ChurnRecoveryCampaign $campaign): array {
        return [
            'readings' => $user->readingLogs()->orderBy('chapter')->get(['book_id', 'chapter', 'date_read', 'notes_text'])->toArray(),
            'progress' => BookProgress::query()->where('user_id', $user->id)->firstOrFail()->only([
                'book_id',
                'chapters_read',
                'completion_percent',
                'is_completed',
            ]),
            'celebrated_first_reading' => $user->fresh()->celebrated_first_reading_at !== null,
            'onboarding_steps' => $user->onboardingStepEvents()->pluck('step')->map->value->sort()->values()->all(),
            'achievements' => $user->achievements()->orderBy('achievement_key')->pluck('achievement_key')->all(),
            'campaign_reactivated' => $campaign->fresh()->reactivated_at !== null,
            'campaign_completed' => $campaign->fresh()->completed_at !== null,
        ];
    };

    expect($domainState($apiUser, $apiCampaign))->toBe($domainState($webUser, $webCampaign))
        ->and($apiUser->onboardingStepEvents()->where('step', OnboardingStep::FirstReadingCompleted)->exists())->toBeTrue();
});
