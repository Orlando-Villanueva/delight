<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('celebrated_first_reading_at')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('reading_logs')
                    ->whereColumn('reading_logs.user_id', 'users.id');
            })
            ->update(['celebrated_first_reading_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')
            ->whereNotNull('celebrated_first_reading_at')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('reading_logs')
                    ->whereColumn('reading_logs.user_id', 'users.id');
            })
            ->update(['celebrated_first_reading_at' => null]);
    }
};
