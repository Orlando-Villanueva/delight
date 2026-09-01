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
        Schema::table('announcements', function (Blueprint $table) {
            $table->timestamp('email_broadcast_authorized_at')->nullable()->after('sent_via_email_at');
            $table->timestamp('email_audience_finalized_at')->nullable()->after('email_broadcast_authorized_at');
            $table->index(
                ['email_broadcast_authorized_at', 'starts_at', 'email_audience_finalized_at'],
                'idx_announcement_email_due'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex('idx_announcement_email_due');
            $table->dropColumn([
                'email_broadcast_authorized_at',
                'email_audience_finalized_at',
            ]);
        });
    }
};
