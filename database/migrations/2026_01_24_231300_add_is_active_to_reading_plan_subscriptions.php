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
        Schema::table('reading_plan_subscriptions', function (Blueprint $table) {
            $table->boolean('is_active')->default(false)->after('started_at');
            $table->index(['user_id', 'is_active']);
        });

        // Since only one plan exists in production, we can safely mark all existing subscriptions as active.
        DB::table('reading_plan_subscriptions')->update(['is_active' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reading_plan_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_active']);
            $table->dropColumn('is_active');
        });
    }
};
