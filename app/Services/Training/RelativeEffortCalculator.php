<?php

namespace App\Services\Training;

use App\Models\User;
use App\Models\Workout;

/**
 * Relative Effort — ukuran beban latihan satu aktivitas dari data HR per-detik.
 *
 * RAGA tidak memakai rumus TRIMP Banister (butuh HR istirahat & HR reserve yang
 * tidak disimpan di skema ini). Yang dipakai adalah TRIMP zona Edwards:
 *
 *     Relative Effort = Σ (menit di zona i × bobot zona i),  bobot 1..5
 *
 * Zona HR dihitung `HeartRateZoneService` dari data HR pengguna sendiri
 * (bukan rumus umur), dan durasi tiap zona dibobot waktu antar-sampel sehingga
 * interval sampling yang tidak seragam tidak menggeser hasil. Model ini dipilih
 * karena transparan dan hanya butuh data yang memang sudah dimiliki app.
 *
 * Nilai `null` berarti tidak bisa dihitung (tidak ada HR, atau < 2 sampel),
 * BUKAN nol.
 */
class RelativeEffortCalculator
{
    /** Bobot zona Edwards (Z1..Z5). */
    private const ZONE_WEIGHTS = [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5];

    /** Minimal dua sampel HR supaya ada durasi yang bisa diatribusikan. */
    private const MIN_SAMPLES = 2;

    /** @var array<int, ?int> cache HR maksimum per user (id => bpm|null) */
    private array $maxHeartRateCache = [];

    public function __construct(private HeartRateZoneService $heartRateZones) {}

    /**
     * Hitung Relative Effort dari detik per zona (murni, tanpa DB).
     *
     * @param  array<int, int>  $secondsByZone  zona (1-5) => detik
     */
    public function fromZoneSeconds(array $secondsByZone): ?int
    {
        $totalSeconds = array_sum($secondsByZone);

        if ($totalSeconds <= 0) {
            return null;
        }

        $score = 0.0;

        foreach (self::ZONE_WEIGHTS as $zone => $weight) {
            $score += (($secondsByZone[$zone] ?? 0) / 60) * $weight;
        }

        return (int) round($score);
    }

    /**
     * Hitung Relative Effort sebuah aktivitas, tanpa menyimpan.
     */
    public function forWorkout(Workout $workout): ?int
    {
        $maxHr = $this->maxHeartRateFor($workout->user);

        if ($maxHr === null) {
            return null;
        }

        return $this->forWorkoutWithMaxHeartRate($workout, $maxHr);
    }

    /**
     * Varian yang memakai HR maksimum yang sudah diketahui (menghindari
     * agregasi berulang saat memproses banyak aktivitas sekaligus).
     */
    public function forWorkoutWithMaxHeartRate(Workout $workout, int $maxHeartRate): ?int
    {
        $samples = $workout->samples()
            ->whereNotNull('heart_rate')
            ->orderBy('timestamp')
            ->get(['timestamp', 'heart_rate'])
            ->map(fn ($sample) => [
                'timestamp' => $sample->timestamp,
                'heart_rate' => (float) $sample->heart_rate,
            ])
            ->all();

        if (count($samples) < self::MIN_SAMPLES) {
            return null;
        }

        return $this->fromZoneSeconds($this->heartRateZones->bucketSamples($samples, $maxHeartRate));
    }

    /**
     * Hitung lalu simpan ke kolom `relative_effort`. Mengembalikan nilai baru
     * (null bila tidak bisa dihitung), sehingga pemanggil bisa melaporkan
     * berapa aktivitas yang berhasil diisi.
     */
    public function updateWorkout(Workout $workout): ?int
    {
        $value = $this->forWorkout($workout);

        $workout->forceFill(['relative_effort' => $value])->save();

        return $value;
    }

    /**
     * HR maksimum estimasi milik user, di-cache per instance agar impor
     * puluhan aktivitas tidak mengulang agregasi yang sama.
     */
    private function maxHeartRateFor(User $user): ?int
    {
        if (! array_key_exists($user->id, $this->maxHeartRateCache)) {
            $this->maxHeartRateCache[$user->id] = $this->heartRateZones->estimatedMaxHeartRate($user);
        }

        return $this->maxHeartRateCache[$user->id];
    }
}
