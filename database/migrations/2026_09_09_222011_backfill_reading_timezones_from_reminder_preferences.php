<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNull('reading_timezone')
            ->whereIn('push_notification_timezone', DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC))
            ->update(['reading_timezone' => DB::raw('push_notification_timezone')]);
    }

    public function down(): void
    {
        // Retain established calendars; rolling back the schema migration removes the column.
    }
};
