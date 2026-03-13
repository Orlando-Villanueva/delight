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
            $table->foreignId('churn_recovery_campaign_id')
                ->nullable()
                ->after('user_id')
                ->constrained('churn_recovery_campaigns')
                ->nullOnDelete();
        });

        DB::statement('DROP INDEX IF EXISTS unique_active_user_email_number');
        DB::statement('CREATE UNIQUE INDEX unique_active_user_email_number
            ON churn_recovery_emails (user_id, coalesce(churn_recovery_campaign_id, 0), email_number)
            WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('churn_recovery_emails', function (Blueprint $table) {
            $table->dropConstrainedForeignId('churn_recovery_campaign_id');
        });

        DB::statement('DROP INDEX IF EXISTS unique_active_user_email_number');
        DB::statement('CREATE UNIQUE INDEX unique_active_user_email_number
            ON churn_recovery_emails (user_id, email_number)
            WHERE deleted_at IS NULL');
    }
};
