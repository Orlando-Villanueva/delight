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
        Schema::table('churn_recovery_emails', function (Blueprint $table) {
            $table->dropUnique('unique_user_email_number');
            $table->softDeletes();
        });

        // Add partial unique index (only for non-deleted records)
        // We use raw SQL because Laravel schema builder doesn't support partial indexes directly
        // Must run AFTER Schema::table since softDeletes column needs to exist
        DB::statement('CREATE UNIQUE INDEX unique_active_user_email_number 
            ON churn_recovery_emails (user_id, email_number) 
            WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the partial index
        DB::statement('DROP INDEX IF EXISTS unique_active_user_email_number');

        Schema::table('churn_recovery_emails', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->unique(['user_id', 'email_number'], 'unique_user_email_number');
        });
    }
};
