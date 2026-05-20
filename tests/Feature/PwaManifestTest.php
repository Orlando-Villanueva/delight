<?php

use App\Models\ReadingPlan;
use App\Models\User;

const TODAY_PLAN_SHORTCUT_URL = '/plans/today';

beforeEach(function () {
    $this->manifest = json_decode(file_get_contents(public_path('pwa.webmanifest')), true, flags: JSON_THROW_ON_ERROR);
});

test('manifest screenshots reference existing assets with accurate dimensions', function () {
    expect($this->manifest['screenshots'])->toHaveCount(2);
    expect(array_column($this->manifest['screenshots'], 'src'))->toBe([
        '/images/screenshots/mobile-v3.png',
        '/images/screenshots/desktop-v3.png',
    ]);

    foreach ($this->manifest['screenshots'] as $screenshot) {
        $path = public_path(ltrim($screenshot['src'], '/'));

        expect($path)->toBeFile();

        $image = getimagesize($path);

        expect($image)->not->toBeFalse()
            ->and($image['mime'])->toBe($screenshot['type'])
            ->and($screenshot['sizes'])->toBe($image[0].'x'.$image[1]);
    }
});

test('manifest defines installable app shortcuts for core actions', function () {
    expect($this->manifest['shortcuts'])->toHaveCount(3);

    $expectedShortcuts = [
        [
            'name' => 'Log Reading',
            'short_name' => 'Log',
            'url' => '/logs/create',
        ],
        [
            'name' => 'Today\'s Plan',
            'short_name' => 'Today',
            'url' => TODAY_PLAN_SHORTCUT_URL,
        ],
        [
            'name' => 'Reading History',
            'short_name' => 'History',
            'url' => '/logs',
        ],
    ];

    foreach ($expectedShortcuts as $index => $expectedShortcut) {
        $shortcut = $this->manifest['shortcuts'][$index];

        expect($shortcut)
            ->toMatchArray($expectedShortcut)
            ->and($shortcut['icons'])->toHaveCount(1);

        $icon = $shortcut['icons'][0];
        $path = public_path(ltrim($icon['src'], '/'));

        expect($path)->toBeFile();

        $image = getimagesize($path);

        expect($image)->not->toBeFalse()
            ->and($image['mime'])->toBe($icon['type'])
            ->and($icon['sizes'])->toBe($image[0].'x'.$image[1]);
    }
});

test('manifest shortcut urls resolve for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    foreach ($this->manifest['shortcuts'] as $shortcut) {
        $response = $this->get($shortcut['url']);

        if ($shortcut['url'] === TODAY_PLAN_SHORTCUT_URL) {
            $response->assertRedirect(route('plans.index'));

            continue;
        }

        $response->assertSuccessful();
    }

    $plan = ReadingPlan::factory()->create();

    $user->readingPlanSubscriptions()->create([
        'reading_plan_id' => $plan->id,
        'started_at' => today(),
        'is_active' => true,
    ]);

    $this->get(TODAY_PLAN_SHORTCUT_URL)
        ->assertRedirect(route('plans.today', $plan));
});
