<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workout;
use App\Services\Dashboard\WeeklyTrainingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Susunan baru dashboard (Command Center) — memastikan setiap seksi yang
 * menggantikan kartu lama benar-benar dirender, dan deret 7 hari terakhir
 * dihitung dari data nyata.
 */
class DashboardLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_the_command_center_sections(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Minggu Ini // Beban Aerobik')
            ->assertSee('7 Hari Terakhir // Puncak Beban')
            ->assertSee('Kondisi Fisiologis')
            ->assertSee('Athlete Cognitive Engine')
            ->assertSee('Sesi Kronologis')
            ->assertSee('Hari Ini // Biometrik')
            ->assertSee('Belum ada aktivitas tercatat.');
    }

    public function test_last_seven_days_always_returns_seven_ordered_days(): void
    {
        $user = User::factory()->create();

        $this->travelTo(Carbon::parse('2026-09-13 12:00:00'));

        $series = app(WeeklyTrainingService::class)->lastSevenDays($user);

        $this->assertCount(7, $series);
        $this->assertSame('2026-09-07', $series[0]['date']);
        $this->assertSame('2026-09-13', $series[6]['date']);
        $this->assertSame(0, $series[6]['count']);
    }

    public function test_last_seven_days_sums_distance_and_relative_effort_per_day(): void
    {
        $user = User::factory()->create();

        $this->travelTo(Carbon::parse('2026-09-13 12:00:00'));

        Workout::create([
            'user_id' => $user->id,
            'type' => 'running',
            'start_date' => Carbon::parse('2026-09-12 07:00:00'),
            'end_date' => Carbon::parse('2026-09-12 08:00:00'),
            'distance_meters' => 10500,
            'relative_effort' => 145,
            'source' => 'garmin',
        ]);

        $series = collect(app(WeeklyTrainingService::class)->lastSevenDays($user))->keyBy('date');

        $this->assertSame(10500.0, $series['2026-09-12']['distance_meters']);
        $this->assertSame(145, $series['2026-09-12']['relative_effort']);
        $this->assertSame(1, $series['2026-09-12']['count']);
        $this->assertSame(0, $series['2026-09-13']['count']);
    }

    public function test_peaks_card_falls_back_to_distance_when_no_relative_effort_exists(): void
    {
        $user = User::factory()->create();

        Workout::create([
            'user_id' => $user->id,
            'type' => 'running',
            'start_date' => Carbon::now()->subDay()->setTime(7, 0),
            'end_date' => Carbon::now()->subDay()->setTime(8, 0),
            'distance_meters' => 8000,
            'source' => 'garmin',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Jarak harian (km)');
    }
}
