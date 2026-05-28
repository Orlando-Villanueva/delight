<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('push_notifications_enabled_at')->nullable();
            $table->timestamp('daily_reading_reminder_enabled_at')->nullable();
            $table->timestamp('streak_warning_enabled_at')->nullable();
            $table->string('push_notification_timezone')->nullable();
            $table->timestamp('reading_reminders_prompt_dismissed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'push_notifications_enabled_at',
                'daily_reading_reminder_enabled_at',
                'streak_warning_enabled_at',
                'push_notification_timezone',
                'reading_reminders_prompt_dismissed_at',
            ]);
        });
    }
};
