<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automated_email_capacity_guards', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
        });
        DB::table('automated_email_capacity_guards')->insert(['id' => 1]);

        Schema::create('automated_email_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('purpose');
            $table->timestamp('reserved_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automated_email_reservations');
        Schema::dropIfExists('automated_email_capacity_guards');
    }
};
