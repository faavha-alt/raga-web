<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'name', 'description', 'activity_type', 'distance_meters',
    'elevation_gain_meters', 'average_grade_percent', 'start_lat', 'start_lng',
    'end_lat', 'end_lng', 'start_label', 'end_label', 'encoded_polyline',
    'is_public', 'effort_count',
])]
class Segment extends Model
{
    protected function casts(): array
    {
        return [
            'distance_meters' => 'float',
            'elevation_gain_meters' => 'float',
            'average_grade_percent' => 'float',
            'start_lat' => 'float',
            'start_lng' => 'float',
            'end_lat' => 'float',
            'end_lng' => 'float',
            'is_public' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function efforts(): HasMany
    {
        return $this->hasMany(SegmentEffort::class);
    }

    /**
     * Jarak dalam kilometer, untuk tampilan.
     */
    public function distanceKm(): float
    {
        return round($this->distance_meters / 1000, 2);
    }
}
