<?php

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

it('shares only ten visible summaries while counting all unread announcements', function () {
    $user = User::factory()->create();
    $items = collect(range(1, 12))->map(fn ($day) => Announcement::factory()->create([
        'title' => "Reading update {$day}",
        'starts_at' => now()->subDays($day),
    ]));
    Announcement::factory()->draft()->create();
    Announcement::factory()->create(['starts_at' => now()->addDay()]);
    Announcement::factory()->create(['starts_at' => now()->subDays(3), 'ends_at' => now()->subDay()]);
    $user->announcements()->attach($items[0], ['read_at' => now()]);
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        if (str_contains($query->sql, 'announcements')) {
            $queries[] = $query->sql;
        }
    });

    $response = $this->actingAs($user)->get(route('feedback.create'));
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('reader.announcements', 10)
        ->where('reader.unreadCount', 11)
        ->where('reader.announcements.0.id', $items[0]->id)
        ->where('reader.announcements.0.isRead', true)
        ->where('reader.announcements.1.isRead', false)
        ->where('reader.announcements.9.id', $items[9]->id)
        ->where('reader.announcements.0.url', route('announcements.show', $items[0]->slug))
        ->where('reader.urls.updates', route('announcements.index'))
        ->missing('reader.announcements.0.content'));

    // One count across visible announcements and one bounded summary query, including read status.
    expect($queries)->toHaveCount(2);
    expect($user->announcements()->count())->toBe(1);
});

it('keeps read status individual and refreshes it after an article visit', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $announcement = Announcement::factory()->create();
    $this->actingAs($first)->get(route('announcements.show', $announcement->slug))->assertOk();
    $this->get(route('feedback.create'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('reader.unreadCount', 0)->where('reader.announcements.0.isRead', true));
    $this->actingAs($second)->get(route('feedback.create'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('reader.unreadCount', 1)->where('reader.announcements.0.isRead', false));
});

it('shares an empty list and retains the legacy HTML consumer', function () {
    $this->actingAs(User::factory()->create())->get(route('feedback.create'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('reader.unreadCount', 0)->has('reader.announcements', 0));
    $announcement = Announcement::factory()->create(['title' => 'A legacy update']);
    $this->get(route('notifications.index'))->assertOk()->assertSee($announcement->title);
});
