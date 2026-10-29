<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idiario_sync_states', function (Blueprint $table) {
            $table->id();
            $table->timestamp('requested_at')->nullable();
            $table->string('status', 20)->default('idle');
            $table->unsignedSmallInteger('done_percentage')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('diary_started_at')->nullable();
            $table->boolean('sync_again')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idiario_sync_states');
    }
};
