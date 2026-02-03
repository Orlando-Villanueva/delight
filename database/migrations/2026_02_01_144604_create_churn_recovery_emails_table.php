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
        Schema::create('churn_recovery_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->tinyInteger('email_number')->unsigned(); // 1, 2, or 3
            $table->timestamp('sent_at');
            $table->timestamps();

            // Prevent duplicate emails
            $table->unique(['user_id', 'email_number'], 'unique_user_email_number');
            $table->index(['user_id', 'sent_at'], 'idx_user_sent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('churn_recovery_emails');
    }
};
