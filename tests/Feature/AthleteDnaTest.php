<?php

namespace Tests\Feature;

use App\Models\PersonalRecord;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AthleteDnaTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('athlete.dna'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_open_the_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('athlete.dna'))
            ->assertOk()
            ->assertSee('ATHLETE DNA')
            ->assertSee('Exertion Grid')
            ->assertSee('Discipline Ratio')
            ->assertSee('Radar 5 Pilar Fisiologis')
            ->assertSee('Perbandingan 28 Hari')
            ->assertSee('Personal Records Suite');
    }

    public function test_exertion_grid_always_covers_364_days(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('athlete.dna'));

        $response->assertOk();
        $response->assertViewHas('exertion', function (array $exertion): bool {
            $cells = collect($exertion['weeks'])->flatten(1);

            // 364 sel ber-tanggal: sisanya padding agar baris pertama Senin.
            return $cells->filter(fn (array $day): bool => $day['date'] !== null)->count() === 364
                && $cells->every(fn (array $day): bool => $day['date'] === null || $day['level'] !== null);
        });
    }

    public function test_exertion_grid_falls_back_to_training_load_when_relative_effort_is_zero(): void
    {
        $user = User::factory()->create();

        Workout::create([
            'user_id' => $user->id,
            'type' => 'running',
            'start_date' => Carbon::today()->setTime(7, 0),
            'end_date' => Carbon::today()->setTime(8, 0),
            'training_load' => 80,
            'relative_effort' => 0,
            'source' => 'garmin',
        ]);

        $this->actingAs($user)
            ->get(route('athlete.dna'))
            ->assertOk()
            ->assertViewHas('exertion', fn (array $exertion): bool => $exertion['metric'] === 'training_load'
                && $exertion['active_days'] === 1);
    }

    public function test_exertion_grid_prefers_relative_effort_when_present(): void
    {
        $user = User::factory()->create();

        Workout::create([
            'user_id' => $user->id,
            'type' => 'running',
            'start_date' => Carbon::today()->setTime(7, 0),
            'end_date' => Carbon::today()->setTime(8, 0),
            'training_load' => 80,
            'relative_effort' => 120,
            'source' => 'garmin',
        ]);

        $this->actingAs($user)
            ->get(route('athlete.dna'))
            ->assertViewHas('exertion', fn (array $exertion): bool => $exertion['metric'] === 'relative_effort'
                && $exertion['total_score'] === 120.0);
    }

    public function test_radar_returns_null_pillars_and_does_not_crash_without_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('athlete.dna'));

        $response->assertOk();
        $response->assertSee('Belum ada pilar yang bisa dihitung');
        $response->assertViewHas('pillars', function (array $pillars): bool {
            if (count($pillars) !== 5) {
                return false;
            }

            foreach ($pillars as $pillar) {
                if ($pillar['score'] !== null) {
                    return false;
                }
            }

            return true;
        });
    }

    public function test_radar_scores_computed_from_real_data(): void
    {
        $user = User::factory()->create();
        $today = Carbon::today();

        Workout::create([
            'user_id' => $user->id,
            'type' => 'running',
            'start_date' => $today->copy()->setTime(7, 0),
            'end_date' => $today->copy()->setTime(8, 0),
            'distance_meters' => 10000,
            'relative_effort' => 100,
            'source' => 'garmin',
        ]);

        $user->recoveryScores()->create(['date' => $today->toDateString(), 'score' => 70, 'calculated_at' => now()]);
        $user->readinessScores()->create(['date' => $today->toDateString(), 'score' => 60, 'calculated_at' => now()]);
        $user->vitalMeasurements()->create(['type' => 'vo2max', 'value' => 50, 'unit' => 'ml/kg/min', 'date' => $today, 'source' => 'garmin']);
        $user->trainingLoads()->create(['date' => $today->toDateString(), 'acute_chronic_ratio' => 1.0, 'risk_level' => 'optimal']);

        $this->actingAs($user)
            ->get(route('athlete.dna'))
            ->assertOk()
            ->assertViewHas('pillars', function (array $pillars): bool {
                $byKey = collect($pillars)->keyBy('key');

                // VO2max 50 → (50-30)/(70-30)*100 = 50; ACWR 1.0 → 100.
                return $byKey['aerobic']['score'] === 50
                    && $byKey['recovery']['score'] === 70
                    && $byKey['readiness']['score'] === 60
                    && $byKey['balance']['score'] === 100;
            });
    }

    public function test_comparison_uses_null_percent_when_previous_period_is_zero(): void
    {
        $user = User::factory()->create();
        $today = Carbon::today();

        // Hanya 28 hari terakhir yang berisi data → periode pembanding nol.
        Workout::create([
            'user_id' => $user->id,
            'type' => 'running',
            'start_date' => $today->copy()->subDays(3)->setTime(7, 0),
            'end_date' => $today->copy()->subDays(3)->setTime(8, 0),
            'distance_meters' => 10000,
            'elevation_gain_meters' => 100,
            'relative_effort' => 90,
            'source' => 'garmin',
        ]);

        $this->actingAs($user)
            ->get(route('athlete.dna'))
            ->assertOk()
            ->assertViewHas('comparison', function (array $comparison): bool {
                if (count($comparison) !== 3) {
                    return false;
                }

                foreach ($comparison as $cell) {
                    if ($cell['percent'] !== null || $cell['direction'] !== 'flat') {
                        return false;
                    }
                }

                return true;
            });
    }

    public function test_comparison_reports_growth_against_a_non_zero_baseline(): void
    {
        $user = User::factory()->create();
        $today = Carbon::today();

        foreach ([35 => 10000, 3 => 20000] as $daysAgo => $distance) {
            Workout::create([
                'user_id' => $user->id,
                'type' => 'running',
                'start_date' => $today->copy()->subDays($daysAgo)->setTime(7, 0),
                'end_date' => $today->copy()->subDays($daysAgo)->setTime(8, 0),
                'distance_meters' => $distance,
                'relative_effort' => 100,
                'source' => 'garmin',
            ]);
        }

        $this->actingAs($user)
            ->get(route('athlete.dna'))
            ->assertViewHas('comparison', function (array $comparison): bool {
                $distance = collect($comparison)->firstWhere('key', 'distance');

                // 10 km → 20 km = +100%.
                return $distance['percent'] === 100.0 && $distance['direction'] === 'up';
            });
    }

    public function test_personal_records_are_formatted_and_limited_to_six_cards(): void
    {
        $user = User::factory()->create();
        $today = Carbon::today();

        PersonalRecord::create(['user_id' => $user->id, 'type' => 'fastest_5k', 'value' => 1234, 'unit' => 'running_raw', 'achieved_date' => $today]);
        PersonalRecord::create(['user_id' => $user->id, 'type' => 'longest_run', 'value' => 21500, 'unit' => 'running_raw', 'achieved_date' => $today]);
        PersonalRecord::create(['user_id' => $user->id, 'type' => 'garmin_pr_type_12', 'value' => 42, 'unit' => 'cycling_raw', 'achieved_date' => $today]);

        $this->actingAs($user)
            ->get(route('athlete.dna'))
            ->assertOk()
            ->assertViewHas('records', function (array $records): bool {
                $byType = collect($records)->keyBy('type');

                return count($records) === 3
                    && $byType['fastest_5k']['value_text'] === '20:34'
                    && $byType['fastest_5k']['source'] === 'Garmin'
                    && $byType['longest_run']['value_text'] === '21.50 km'
                    && $byType['longest_run']['unit_text'] === ''
                    && $byType['garmin_pr_type_12']['label'] === 'Garmin PR #12';
            });
    }

    public function test_page_loads_with_seeded_data_without_lazy_loading(): void
    {
        $user = User::factory()->create();
        $today = Carbon::today();

        for ($i = 0; $i < 40; $i += 3) {
            Workout::create([
                'user_id' => $user->id,
                'type' => $i % 6 === 0 ? 'trail_running' : 'running',
                'start_date' => $today->copy()->subDays($i)->setTime(7, 0),
                'end_date' => $today->copy()->subDays($i)->setTime(8, 30),
                'distance_meters' => 12000,
                'elevation_gain_meters' => 250,
                'average_pace_seconds_per_km' => 330,
                'relative_effort' => 140,
                'training_load' => 70,
                'source' => 'garmin',
            ]);
        }

        Model::preventLazyLoading();

        try {
            $this->actingAs($user)
                ->get(route('athlete.dna'))
                ->assertOk()
                ->assertSee('Trail Running');
        } finally {
            Model::preventLazyLoading(false);
        }
    }
}
