<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('sleep_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('activity_summary_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('health_score_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recovery_score_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_summaries');
    }
};
