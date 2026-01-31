<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
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
            ->update(['celebrated_first_reading_at' => Carbon::parse('2026-01-31 14:29:26')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only rollback rows that were set by this backfill (exact timestamp match)
        // This prevents wiping out legitimate celebration timestamps set by normal app usage
        DB::table('users')
            ->where('celebrated_first_reading_at', Carbon::parse('2026-01-31 14:29:26'))
            ->update(['celebrated_first_reading_at' => null]);
    }
};
