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
        Schema::create('native_push_reminder_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('native_push_registration_id')
                ->constrained('native_push_registrations')
                ->cascadeOnDelete();
            $table->string('reminder_type', 32);
            $table->date('reminder_date');
            $table->timestamp('scheduled_for_at');
            $table->char('token_hash', 64);
            $table->string('expo_ticket_id')->nullable();
            $table->string('expo_ticket_error_code', 64)->nullable();
            $table->text('expo_ticket_error_message')->nullable();
            $table->string('expo_receipt_status', 16)->nullable();
            $table->string('expo_receipt_error')->nullable();
            $table->timestamp('expo_receipt_checked_at')->nullable();
            $table->unsignedTinyInteger('expo_retry_count')->default(0);
            $table->timestamp('expo_retry_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->unique(
                ['native_push_registration_id', 'reminder_type', 'reminder_date'],
                'unique_native_push_reminder_date'
            );
            $table->index(
                ['expo_receipt_status', 'sent_at', 'expo_receipt_checked_at'],
                'idx_native_push_receipts'
            );
            $table->index(['expo_receipt_status', 'expo_retry_at'], 'idx_native_push_retry_receipts');
            $table->index(['reminder_type', 'reminder_date'], 'idx_native_push_reminder_type_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('native_push_reminder_deliveries');
    }
};
