<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNull('reading_timezone')
            ->whereIn('push_notification_timezone', DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC))
            ->update(['reading_timezone' => DB::raw('push_notification_timezone')]);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('push_notification_timezone');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'push_notification_timezone')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('push_notification_timezone')->nullable();
            });
        }

        DB::table('users')
            ->whereNull('push_notification_timezone')
            ->whereIn('reading_timezone', DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC))
            ->update(['push_notification_timezone' => DB::raw('reading_timezone')]);
    }
};
