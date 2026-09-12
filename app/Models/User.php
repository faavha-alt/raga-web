<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'username', 'email', 'password', 'avatar_path', 'bio', 'location', 'is_public'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
            'is_public' => 'boolean',
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

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }

    public function aiConversations(): HasMany
    {
        return $this->hasMany(AiConversation::class);
    }

    public function aiSetting(): HasOne
    {
        return $this->hasOne(AiSetting::class);
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

    /**
     * Baris `follows` di mana pengguna ini adalah pengikutnya.
     */
    public function following(): HasMany
    {
        return $this->hasMany(Follow::class, 'follower_id');
    }

    /**
     * Baris `follows` di mana pengguna ini diikuti.
     */
    public function followers(): HasMany
    {
        return $this->hasMany(Follow::class, 'following_id');
    }

    /**
     * Pengguna yang diikuti (relasi many-to-many langsung).
     */
    public function followingUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id')
            ->withTimestamps();
    }

    /**
     * Pengguna yang mengikuti pengguna ini.
     */
    public function followerUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id')
            ->withTimestamps();
    }

    public function kudos(): HasMany
    {
        return $this->hasMany(Kudos::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function ownedSegments(): HasMany
    {
        return $this->hasMany(Segment::class);
    }

    public function segmentEfforts(): HasMany
    {
        return $this->hasMany(SegmentEffort::class);
    }

    /**
     * Apakah pengguna ini mengikuti $other.
     */
    public function isFollowing(User $other): bool
    {
        return $this->following()->where('following_id', $other->id)->exists();
    }

    /**
     * Inisial nama untuk avatar fallback.
     */
    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $initials = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $initials !== '' ? $initials : '?';
    }

    /**
     * URL avatar publik, null bila pengguna belum mengunggah foto.
     */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? asset($this->avatar_path) : null;
    }

    /**
     * Apakah akun ini punya password sendiri.
     *
     * Akun yang lahir dari Google tidak punya password (kolomnya NULL), sehingga
     * tidak ada hash yang bisa dibandingkan oleh aturan validasi `current_password`.
     * Halaman ganti password dan hapus akun memakai ini untuk melewati verifikasi
     * tersebut — tanpa itu, pengguna Google tidak akan pernah bisa memasang
     * password atau menghapus akunnya sendiri.
     */
    public function hasPassword(): bool
    {
        return $this->password !== null && $this->password !== '';
    }

    /**
     * Passport's TokenGuard calls this unconditionally on every OAuth-authenticated
     * request. We deliberately don't pull in Passport's HasApiTokens trait here — its
     * methods (tokens(), createToken(), etc.) collide by name with Sanctum's, which
     * this app already uses for the plain REST API. This is the one method Passport
     * actually needs at runtime; scopes aren't used anywhere, so it's a no-op.
     */
    public function withAccessToken($accessToken): static
    {
        return $this;
    }
}
