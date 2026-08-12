<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planned_workouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_day_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->double('duration_minutes')->nullable();
            $table->double('distance_meters')->nullable();
            $table->double('target_pace_seconds_per_km')->nullable();
            $table->unsignedTinyInteger('target_heart_rate_zone')->nullable();
            $table->string('intensity')->nullable();
            $table->text('warm_up')->nullable();
            $table->text('main_set')->nullable();
            $table->text('cool_down')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('planned');
            $table->text('adapted_from_description')->nullable();
            $table->text('adapted_reason')->nullable();
            $table->timestamps();

            $table->index(['training_day_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planned_workouts');
    }
};
