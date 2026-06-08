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
        Schema::table('reading_plan_subscriptions', function (Blueprint $table) {
            $table->unsignedSmallInteger('start_day')->default(1)->after('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reading_plan_subscriptions', function (Blueprint $table) {
            $table->dropColumn('start_day');
        });
    }
};
