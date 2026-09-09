<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('native_push_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('personal_access_token_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('expo_push_token');
            $table->string('token_hash', 64)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('native_push_registrations');
    }
};
