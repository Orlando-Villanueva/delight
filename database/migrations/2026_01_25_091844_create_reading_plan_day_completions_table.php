<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reading_plan_day_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reading_log_id')->constrained()->onDelete('cascade');
            $table->foreignId('reading_plan_subscription_id')->constrained()->onDelete('cascade');
            $table->unsignedSmallInteger('reading_plan_day');
            $table->timestamps();

            // Same reading log can only be linked to a subscription once
            $table->unique(['reading_log_id', 'reading_plan_subscription_id'], 'unique_log_subscription');

            // Index for efficient completion queries
            $table->index(['reading_plan_subscription_id', 'reading_plan_day'], 'idx_subscription_day');
        });

        // Move existing reading_log plan associations to junction table
        DB::table('reading_logs')
            ->whereNotNull('reading_plan_subscription_id')
            ->orderBy('id')
            ->chunk(1000, function ($logs) {
                $completions = $logs->map(fn ($log) => [
                    'reading_log_id' => $log->id,
                    'reading_plan_subscription_id' => $log->reading_plan_subscription_id,
                    'reading_plan_day' => $log->reading_plan_day,
                    'created_at' => $log->created_at,
                    'updated_at' => $log->updated_at,
                ])->toArray();

                DB::table('reading_plan_day_completions')->insert($completions);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reading_plan_day_completions');
    }
};
