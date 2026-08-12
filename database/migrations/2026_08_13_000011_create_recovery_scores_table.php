<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recovery_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedTinyInteger('score');
            $table->double('hrv_contribution')->nullable();
            $table->double('resting_heart_rate_contribution')->nullable();
            $table->double('sleep_contribution')->nullable();
            $table->double('training_load_contribution')->nullable();
            $table->dateTime('calculated_at');
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recovery_scores');
    }
};
