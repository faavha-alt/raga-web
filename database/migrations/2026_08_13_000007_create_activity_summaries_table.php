<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('steps')->default(0);
            $table->double('distance_meters')->default(0);
            $table->double('active_calories')->default(0);
            $table->double('exercise_minutes')->default(0);
            $table->unsignedTinyInteger('stand_hours')->default(0);
            $table->string('source');
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_summaries');
    }
};
