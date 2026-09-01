<?php

use App\Console\Commands\DispatchReadingReminderPushes;
use App\Console\Commands\SendOnboardingReminders;
use App\Console\Commands\SendPublishedAnnouncementEmails;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(SendOnboardingReminders::class)
    ->dailyAt('09:00')
    ->timezone(config('app.timezone'))
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('churn:send-recovery')
    ->dailyAt('14:00')
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command(DispatchReadingReminderPushes::class)
    ->everyFifteenMinutes()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command(SendPublishedAnnouncementEmails::class)
    ->everyFiveMinutes()
    ->onOneServer()
    ->withoutOverlapping(30)
    ->runInBackground();
