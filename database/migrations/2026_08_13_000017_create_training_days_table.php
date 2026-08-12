<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_week_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->timestamps();

            $table->index(['training_week_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_days');
    }
};
