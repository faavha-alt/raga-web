<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Segment: potongan rute yang diperlombakan (fitur khas Strava).
     *
     * Rute disimpan sebagai polyline ter-encode agar leaderboard dan peta
     * tidak perlu memuat seluruh `workout_samples`.
     */
    public function up(): void
    {
        Schema::create('segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('activity_type', 30)->default('running');
            $table->double('distance_meters');
            $table->double('elevation_gain_meters')->default(0);
            $table->double('average_grade_percent')->default(0);
            $table->double('start_lat');
            $table->double('start_lng');
            $table->double('end_lat');
            $table->double('end_lng');
            $table->string('start_label')->nullable();
            $table->string('end_label')->nullable();
            $table->longText('encoded_polyline')->nullable();
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('effort_count')->default(0);
            $table->timestamps();

            $table->index(['activity_type', 'distance_meters']);
            $table->index(['start_lat', 'start_lng']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('segments');
    }
};
