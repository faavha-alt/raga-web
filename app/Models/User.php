<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function healthScores(): HasMany
    {
        return $this->hasMany(HealthScore::class);
    }

    public function recoveryScores(): HasMany
    {
        return $this->hasMany(RecoveryScore::class);
    }

    public function readinessScores(): HasMany
    {
        return $this->hasMany(ReadinessScore::class);
    }

    public function sleepSessions(): HasMany
    {
        return $this->hasMany(SleepSession::class);
    }

    public function activitySummaries(): HasMany
    {
        return $this->hasMany(ActivitySummary::class);
    }

    public function trainingGoals(): HasMany
    {
        return $this->hasMany(TrainingGoal::class);
    }

    public function trainingPlans(): HasMany
    {
        return $this->hasMany(TrainingPlan::class);
    }

    public function workouts(): HasMany
    {
        return $this->hasMany(Workout::class);
    }

    public function garminConnection(): HasOne
    {
        return $this->hasOne(GarminConnection::class);
    }

    public function vitalMeasurements(): HasMany
    {
        return $this->hasMany(VitalMeasurement::class);
    }

    public function bodyMeasurements(): HasMany
    {
        return $this->hasMany(BodyMeasurement::class);
    }

    public function personalRecords(): HasMany
    {
        return $this->hasMany(PersonalRecord::class);
    }

    public function heartRateSamples(): HasMany
    {
        return $this->hasMany(HeartRateSample::class);
    }

    public function trainingLoads(): HasMany
    {
        return $this->hasMany(TrainingLoad::class);
    }
}
