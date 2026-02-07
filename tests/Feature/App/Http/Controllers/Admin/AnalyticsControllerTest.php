<?php

use App\Models\ChurnRecoveryEmail;
use App\Models\ReadingLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    config(['mail.admin_address' => 'admin@example.com']);

    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('it_can_show_the_analytics_dashboard_for_admins', function () {
    $now = Carbon::create(2026, 2, 6, 12, 0, 0);
    Carbon::setTestNow($now);
    Cache::flush();

    $userA = User::factory()->create([
        'created_at' => $now->copy()->subDays(2),
    ]);
    ReadingLog::factory()->for($userA)->create([
        'date_read' => $now->copy()->subDays(1)->toDateString(),
        'created_at' => $now->copy()->subDays(1),
        'updated_at' => $now->copy()->subDays(1),
    ]);
    ChurnRecoveryEmail::create([
        'user_id' => $userA->id,
        'email_number' => 1,
        'sent_at' => $now->copy()->subDays(5),
    ]);

    $userB = User::factory()->create([
        'created_at' => $now->copy()->subHours(10),
    ]);
    ReadingLog::factory()->for($userB)->create([
        'date_read' => $now->copy()->subHours(2)->toDateString(),
        'created_at' => $now->copy()->subHours(2),
        'updated_at' => $now->copy()->subHours(2),
    ]);
    ChurnRecoveryEmail::create([
        'user_id' => $userB->id,
        'email_number' => 1,
        'sent_at' => $now->copy()->subHour(),
    ]);

    User::factory()->create([
        'created_at' => $now->copy()->subDay(),
    ]);

    $response = $this->actingAs($this->admin)->get(route('admin.analytics.index'));

    $response->assertOk();
    $response->assertSee('Analytics Dashboard');
    $response->assertSee('50.0%');
    $response->assertSee('16.0h');
    $response->assertSee('50.0%');
});

it('it_can_block_non_admins_from_admin_analytics', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
    ]);

    $response = $this->actingAs($user)->get(route('admin.analytics.index'));

    $response->assertForbidden();
});

it('it_can_redirect_guests_from_admin_analytics', function () {
    $response = $this->get(route('admin.analytics.index'));

    $response->assertRedirect(route('login'));
});
