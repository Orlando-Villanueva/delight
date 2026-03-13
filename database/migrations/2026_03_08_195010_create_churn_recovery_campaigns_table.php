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
        Schema::create('churn_recovery_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('campaign_key');
            $table->string('cohort');
            $table->string('variant');
            $table->timestamp('started_at');
            $table->timestamp('observed_until');
            $table->timestamp('reactivated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_touch_sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['campaign_key', 'variant'], 'crc_campaign_variant_idx');
            $table->index(['user_id', 'started_at'], 'crc_user_started_idx');
        });

        DB::statement('CREATE UNIQUE INDEX crc_unique_active_user_campaign
            ON churn_recovery_campaigns (user_id, campaign_key)
            WHERE deleted_at IS NULL AND completed_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS crc_unique_active_user_campaign');
        Schema::dropIfExists('churn_recovery_campaigns');
    }
};
