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
        Schema::table('reading_logs', function (Blueprint $table) {
            // Drop the index first
            $table->dropIndex('idx_plan_subscription_day');

            // Drop foreign key and column
            $table->dropConstrainedForeignId('reading_plan_subscription_id');

            // Drop the day column
            $table->dropColumn('reading_plan_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reading_logs', function (Blueprint $table) {
            $table->foreignId('reading_plan_subscription_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete()
                ->after('user_id');

            $table->unsignedSmallInteger('reading_plan_day')
                ->nullable()
                ->after('reading_plan_subscription_id');

            $table->index(
                ['reading_plan_subscription_id', 'reading_plan_day'],
                'idx_plan_subscription_day'
            );
        });
    }
};
