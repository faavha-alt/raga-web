<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('week_number');
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();

            $table->index(['training_plan_id', 'week_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_weeks');
    }
};
