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
        Schema::create('push_reminder_delivery_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('push_reminder_delivery_id')->nullable()->constrained('push_reminder_deliveries')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reminder_type', 32)->nullable();
            $table->date('reminder_date')->nullable();
            $table->unsignedBigInteger('push_subscription_id')->nullable();
            $table->string('endpoint_host')->nullable();
            $table->char('endpoint_hash', 64)->nullable();
            $table->string('status', 16);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->boolean('expired')->default(false);
            $table->string('failure_reason')->nullable();
            $table->text('response_body')->nullable();
            $table->timestamp('reported_at');
            $table->timestamps();

            $table->index(['push_reminder_delivery_id', 'status'], 'idx_push_report_delivery_status');
            $table->index(['user_id', 'reminder_type', 'reminder_date'], 'idx_push_report_user_reminder');
            $table->index(['push_subscription_id'], 'idx_push_report_subscription');
            $table->index(['endpoint_host', 'status'], 'idx_push_report_endpoint_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('push_reminder_delivery_reports');
    }
};
