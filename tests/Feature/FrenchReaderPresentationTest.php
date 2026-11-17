<?php

use App\Models\ReadingLog;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Models\UserAchievement;
use App\Services\AchievementService;
use App\Services\ReadingCalendarService;
use App\Services\StreakStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('localizes cached English dashboard records from IDs without changing stored reading data', function () {
    $user = User::factory()->create(['preferred_locale' => 'en']);
    $log = ReadingLog::factory()->for($user)->create([
        'book_id' => 43,
        'chapter' => 1,
        'passage_text' => 'John 1',
        'notes_text' => 'A saved note',
        'date_read' => today()->toDateString(),
    ]);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $user->forceFill(['preferred_locale' => 'fr'])->save();
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard', false)
        ->where('recentActivity.0.passageText', 'Jean 1')
        ->where('bookProgress.new_testament.processed_books.0.name', 'Matthieu')
        ->where('calendar.monthName', now()->locale('fr')->isoFormat('MMMM YYYY'))
    );

    expect($log->fresh()->passage_text)->toBe('John 1')
        ->and($log->fresh()->notes_text)->toBe('A saved note');
});

it('keeps dashboard history for Tobit readable after the user disables the deuterocanonical canon', function () {
    $user = User::factory()->create([
        'preferred_locale' => 'fr',
        'deuterocanonical_books_enabled_at' => now(),
    ]);
    ReadingLog::factory()->for($user)->create([
        'book_id' => 67,
        'chapter' => 1,
        'passage_text' => 'Tobit 1',
        'date_read' => today()->toDateString(),
    ]);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $user->forceFill(['deuterocanonical_books_enabled_at' => null])->save();

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard', false)
        ->where('recentActivity.0.passageText', 'Tobie 1')
    );
});

it('invalidates both locale variants of dashboard cache after a new reading', function () {
    $user = User::factory()->create(['preferred_locale' => 'fr']);
    $calendar = app(ReadingCalendarService::class);
    foreach (['en', 'fr'] as $locale) {
        Cache::put($calendar->dashboardStatisticsCacheKey($user, $locale), ['stale' => true], 60);
    }

    $this->actingAs($user)->postJson(route('logs.store'), [
        'book_id' => 43,
        'start_chapter' => 1,
        'date_read' => today()->toDateString(),
        'notes_text' => 'A note.',
    ])->assertSuccessful();

    expect(Cache::missing($calendar->dashboardStatisticsCacheKey($user, 'en')))->toBeTrue()
        ->and(Cache::missing($calendar->dashboardStatisticsCacheKey($user, 'fr')))->toBeTrue();
});

it('localizes newly logged passage presentation while storing the stable English reference', function () {
    $user = User::factory()->create(['preferred_locale' => 'fr']);

    $response = $this->actingAs($user)->postJson(route('logs.store'), [
        'book_id' => 43,
        'start_chapter' => 1,
        'end_chapter' => 3,
        'date_read' => today()->toDateString(),
        'notes_text' => 'Une note privée.',
    ])->assertSuccessful();

    $response->assertJsonPath('reading.passage_text', 'Jean 1-3');
    expect($user->readingLogs()->first()->passage_text)->toBe('John 1-3')
        ->and($user->readingLogs()->first()->notes_text)->toBe('Une note privée.');
});

it('localizes built-in plan titles and derives daily labels from stable book and chapter IDs', function () {
    $user = User::factory()->create(['preferred_locale' => 'fr']);
    ReadingPlan::create([
        'slug' => 'standard-canonical',
        'name' => 'Canonical Reading Plan',
        'description' => 'Read the Bible in canonical order.',
        'days' => [[
            'day' => 1,
            'label' => 'Genesis 1-3',
            'chapters' => [
                ['book_id' => 1, 'book_name' => 'Genesis', 'chapter' => 1],
                ['book_id' => 1, 'book_name' => 'Genesis', 'chapter' => 2],
                ['book_id' => 1, 'book_name' => 'Genesis', 'chapter' => 3],
            ],
        ]],
        'is_active' => true,
    ]);

    $this->actingAs($user)->get(route('plans.index'))->assertInertia(fn (Assert $page) => $page
        ->component('Plans')
        ->where('plans.0.name', 'Ordre canonique')
        ->where('plans.0.description', 'Lisez la Bible dans l’ordre habituel des livres.')
        ->where('plans.0.days.0.optionLabel', 'Genèse 1-3 — jour 1')
    );

    $this->post(route('plans.subscribe', ReadingPlan::first()), ['start_day' => 1])
        ->assertRedirect(route('plans.today', ReadingPlan::first()))
        ->assertSessionHas('success', 'Vous suivez maintenant le plan Ordre canonique.');
});

it('falls back to stored plan copy when the French catalogue has no translation for a plan slug', function () {
    $user = User::factory()->create(['preferred_locale' => 'fr']);
    ReadingPlan::create([
        'slug' => 'custom-plan',
        'name' => 'Custom Reading Plan',
        'description' => 'A user-created plan description.',
        'days' => [['day' => 1, 'label' => 'Genesis 1', 'chapters' => [['book_id' => 1, 'book_name' => 'Genesis', 'chapter' => 1]]]],
        'is_active' => true,
    ]);

    $this->actingAs($user)->get(route('plans.index'))->assertInertia(fn (Assert $page) => $page
        ->component('Plans')
        ->where('plans.0.name', 'Custom')
        ->where('plans.0.description', 'A user-created plan description.')
    );
});

it('maps earned achievement copy from keys and metadata rather than stored English labels', function () {
    $user = User::factory()->create(['preferred_locale' => 'fr']);
    $definition = config('achievements.definitions');
    foreach ([
        ['book_completed', 'book:43:completion:2', ['book_id' => 43, 'completion_number' => 2]],
        ['testament_completed', 'testament:old_testament', ['testament' => 'old_testament', 'completion_number' => 1]],
        ['reading_streak_7', 'streak:7', []],
    ] as [$key, $context, $metadata]) {
        UserAchievement::factory()->for($user)->create([
            'achievement_key' => $key,
            'context_key' => $context,
            'category' => $definition[$key]['category'],
            'display_name' => $definition[$key]['display_name'],
            'description' => $definition[$key]['description'],
            'icon' => $definition[$key]['icon'],
            'style' => $definition[$key]['style'],
            'sort_order' => $definition[$key]['sort_order'],
            'metadata' => $metadata,
            'earned_at' => now(),
        ]);
    }

    $this->actingAs($user)->get(route('achievements.index'))->assertInertia(fn (Assert $page) => $page
        ->component('Achievements')
        ->where('categories.0.achievements.0.name', 'Livre terminé pour la 2e fois : Jean')
        ->where('categories.1.achievements.0.name', 'Ancien Testament terminé')
        ->where('categories.2.achievements.0.name', '7 jours de lecture consécutifs')
    );
});

it('localizes legacy award keys when metadata is empty and preserves singular personal-best grammar', function () {
    app()->setLocale('fr');
    $user = User::factory()->create();
    $book = UserAchievement::factory()->for($user)->create([
        'achievement_key' => 'book_completed', 'context_key' => 'book:8', 'metadata' => [],
        'display_name' => 'Completed Ruth', 'description' => 'You completed Ruth.',
    ]);
    $testament = UserAchievement::factory()->for($user)->create([
        'achievement_key' => 'testament_completed', 'context_key' => 'testament:new', 'metadata' => [],
        'display_name' => 'Testament completed', 'description' => 'You finished a testament.',
    ]);
    $record = UserAchievement::factory()->for($user)->create([
        'achievement_key' => 'personal_best_streak', 'context_key' => 'personal-best:2026-11-13',
        'metadata' => ['current_streak' => 2, 'previous_best' => 1],
        'display_name' => 'Personal best', 'description' => 'You beat your previous best of 1 day.',
    ]);
    $service = app(AchievementService::class);

    expect($service->localizedCopy($book)['display_name'])->toBe('Livre terminé : '.$service->localizedBookName(8))
        ->and($service->localizedCopy($testament)['display_name'])->toBe('Nouveau Testament terminé')
        ->and($service->localizedCopy($record)['description'])->toBe('Vous avez dépassé votre meilleur résultat précédent de 1 jour.')
        ->and($service->localizedCopy($record)['description'])->not->toContain('|', ':previous');
});

it('localizes locked goals, dashboard milestone, celebration and streak messages', function () {
    $user = User::factory()->create(['preferred_locale' => 'fr']);
    $definition = config('achievements.definitions')['reading_streak_7'];
    $achievement = UserAchievement::factory()->for($user)->create([
        'achievement_key' => 'reading_streak_7',
        'context_key' => 'streak:7',
        'category' => $definition['category'],
        'display_name' => $definition['display_name'],
        'description' => $definition['description'],
        'icon' => $definition['icon'],
        'style' => $definition['style'],
        'sort_order' => $definition['sort_order'],
        'metadata' => ['streak_days' => 7],
        'earned_at' => now(),
    ]);
    $log = ReadingLog::factory()->for($user)->create([
        'book_id' => 43, 'chapter' => 1, 'passage_text' => 'John 1', 'date_read' => today()->toDateString(),
    ]);

    $page = $this->actingAs($user)->get(route('achievements.index'));
    $page->assertInertia(fn (Assert $assert) => $assert->component('Achievements')
        ->where('categories.0.achievements.0.name', '7 jours de lecture consécutifs')
        ->where('nextGoals.progress.0.name', 'Première lecture')
        ->where('nextGoals.progress.0.description', 'Ajoutez votre première lecture de la Bible.')
    );

    app()->setLocale('fr');
    $payload = app(AchievementService::class)->getCelebrationPayload($user, collect([$achievement]), $log, false);
    expect($payload['earned'][0]['display_name'])->toBe('7 jours de lecture consécutifs')
        ->and($payload['reading']['passage'])->toBe('Jean 1');

    $streak = app(StreakStateService::class);
    $warning = $streak->getMessagePayload(9, 'warning', 12, false, currentTime: now());
    expect($warning['label'])->toBe('Série en danger')
        ->and($streak->selectMessage(9, 'warning'))->toContain('9 jours');
    $record = $streak->getMessagePayload(13, 'active', 13, true, 'record', true, now());
    expect($record['message'])->toBe('Nouveau record! Votre série de 13 jours devient celle à battre.');
    expect($streak->selectMessage(1, 'active'))->toContain('1 jour')->not->toContain('1 jours')->not->toContain('|');
    $firstRecord = $streak->getMessagePayload(1, 'active', 1, true, 'record', true, now());
    expect($firstRecord['message'])->toBe('Nouveau record! Votre série de 1 jour devient celle à battre.');
});

it('uses singular forms for one remaining chapter or book and the matching testament article', function () {
    app()->setLocale('fr');
    $service = app(AchievementService::class);

    expect($service->localizedPayload([
        'achievement_key' => 'book_completed', 'context_key' => 'book:43', 'display_name' => 'Finish John',
        'description' => '1 chapter left', 'current' => 20, 'target' => 21, 'metadata' => ['book_id' => 43],
    ]))->toMatchArray([
        'display_name' => 'Terminer Jean',
        'description' => 'Il vous reste 1 chapitre pour terminer Jean.',
    ]);

    expect($service->localizedPayload([
        'achievement_key' => 'testament_completed', 'context_key' => 'testament:old_testament', 'display_name' => 'Finish Old Testament',
        'description' => '1 book left', 'current' => 38, 'target' => 39,
    ]))->toMatchArray([
        'display_name' => 'Terminer l’Ancien Testament',
        'description' => 'Il vous reste 1 livre dans l’Ancien Testament.',
    ]);
});

it('has a French rotation variant at every English streak-message index', function () {
    app()->setLocale('fr');
    $english = config('streak_messages');
    $french = __('streak.messages');

    expect(count($english['inactive']['default']))->toBe(count($french['inactive']))
        ->and(count($english['warning']))->toBe(count($french['warning']))
        ->and(count($english['acknowledge']))->toBe(count($french['acknowledge']));

    foreach ($english['active'] as $messages) {
        expect(count($messages))->toBe(count($french['active']));
    }

    foreach ($english['milestone'] as $messages) {
        expect(count($messages))->toBe(count($french['milestone']));
    }
});

it('provides French translations for every tracked English reader catalog key and achievement definition', function () {
    $flatten = function (array $catalog, string $prefix = '') use (&$flatten): array {
        $keys = [];
        foreach ($catalog as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $keys = [...$keys, ...$flatten($value, $path)];
            } else {
                $keys[] = $path;
            }
        }

        return $keys;
    };

    foreach (['reader', 'reading-log', 'streak'] as $catalogName) {
        $english = require lang_path('en/'.$catalogName.'.php');
        $french = require lang_path('fr/'.$catalogName.'.php');
        expect(array_diff($flatten($english), $flatten($french)))->toBe([]);
    }

    $achievements = require lang_path('fr/achievements.php');
    foreach (config('achievements.categories') as $key => $label) {
        expect(array_key_exists($key, $achievements['categories']))->toBeTrue();
    }
    foreach (config('achievements.definitions') as $key => $definition) {
        expect(array_key_exists($key, $achievements['copy']))->toBeTrue();
    }
});

it('uses French validation copy for feedback and settings failures', function () {
    $user = User::factory()->create(['preferred_locale' => 'fr']);

    $this->actingAs($user)->from(route('feedback.create'))->post(route('feedback.store'), [
        'category' => 'bug',
        'message' => '',
    ])->assertRedirect(route('feedback.create'))
        ->assertSessionHasErrors(['message' => 'Le champ message est obligatoire.']);

    $this->from(route('settings.edit'))->patch(route('settings.update'), [
        'preferred_locale' => 'invalid',
    ])->assertRedirect(route('settings.edit'))
        ->assertSessionHasErrors(['preferred_locale' => 'La valeur sélectionnée pour langue est invalide.']);

    $this->patch(route('settings.update'), ['preferred_locale' => 'fr'])
        ->assertRedirect(route('settings.edit'))
        ->assertSessionHas('status', 'Réglages enregistrés.');
});

it('shows French feedback success and failure statuses', function () {
    $user = User::factory()->create(['preferred_locale' => 'fr']);
    Mail::fake();

    $this->actingAs($user)->post(route('feedback.store'), [
        'category' => 'general', 'message' => 'Un commentaire.',
    ])->assertRedirect(route('feedback.create'))
        ->assertSessionHas('status', 'Merci! Votre commentaire a été envoyé.');

    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Transport unavailable'));
    $this->post(route('feedback.store'), [
        'category' => 'bug', 'message' => 'Un autre commentaire.',
    ])->assertRedirect(route('feedback.create'))
        ->assertSessionHasErrors(['submission' => 'Votre commentaire n’a pas pu être envoyé. Veuillez réessayer.']);
});
