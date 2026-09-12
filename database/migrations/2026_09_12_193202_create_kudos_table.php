<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kudos (apresiasi) pada aktivitas.
     */
    public function up(): void
    {
        Schema::create('kudos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workout_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Satu pengguna hanya bisa memberi satu kudos per aktivitas.
            $table->unique(['user_id', 'workout_id']);
            $table->index('workout_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kudos');
    }
};
