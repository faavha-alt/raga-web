<?php

namespace Tests\Feature;

use App\Models\Recommendation;
use App\Models\TrainingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class McpApiWriteBackTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_requires_authentication(): void
    {
        $this->postJson('/api/mcp/training-plan')->assertUnauthorized();
        $this->postJson('/api/mcp/recommendation')->assertUnauthorized();
    }

    public function test_save_training_plan_persists_nested_plan(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/mcp/training-plan', [
            'name' => 'Half Marathon Base Build',
            'start_date' => '2026-09-01',
            'target_date' => '2026-10-01',
            'weeks' => [
                [
                    'week_number' => 1,
                    'start_date' => '2026-09-01',
                    'end_date' => '2026-09-07',
                    'days' => [
                        ['date' => '2026-09-02', 'workouts' => [
                            [
                                'type' => 'running',
                                'distance_meters' => 5000,
                                'target_pace_seconds_per_km' => 360,
                                'target_heart_rate_zone' => 3,
                                'intensity' => 'easy',
                            ],
                        ]],
                        ['date' => '2026-09-03'], // rest day
                    ],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('training_plan_id', 1)
            ->assertJsonPath('weeks_created', 1);

        $plan = $user->trainingPlans()->first();
        $this->assertNotNull($plan);
        $this->assertSame('Half Marathon Base Build', $plan->name);
        $this->assertSame('active', $plan->status);

        $this->assertSame(1, $plan->weeks()->count());
        $this->assertSame(2, $plan->weeks()->first()->days()->count());
        $this->assertSame(1, $plan->weeks()->first()->days()->first()->plannedWorkouts()->count());
        $this->assertSame('running', $plan->weeks()->first()->days()->first()->plannedWorkouts()->first()->type);
    }

    public function test_save_training_plan_is_scoped_to_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/mcp/training-plan', [
            'name' => 'Plan A',
            'start_date' => '2026-09-01',
            'target_date' => '2026-09-07',
            'weeks' => [[
                'week_number' => 1,
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-07',
                'days' => [['date' => '2026-09-01']],
            ]],
        ])->assertOk();

        $this->assertSame(1, $user->trainingPlans()->count());
        $this->assertSame(1, TrainingPlan::count());
    }

    public function test_save_training_plan_rejects_invalid_dates(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/mcp/training-plan', [
            'name' => 'Invalid',
            'start_date' => '2026-10-01',
            'target_date' => '2026-09-01', // before start_date
            'weeks' => [[
                'week_number' => 1,
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-01',
                'days' => [['date' => '2026-09-01']],
            ]],
        ])->assertStatus(422);

        $this->assertSame(0, TrainingPlan::count());
    }

    public function test_save_recommendation_persists_and_defaults_priority(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/mcp/recommendation', [
            'date' => '2026-09-01',
            'category' => 'recovery',
            'title' => 'Easy day',
            'message' => 'Take it easy today to recover.',
        ])->assertOk()
            ->assertJsonPath('recommendation_id', 1)
            ->assertJsonPath('view_url', url('/dashboard'));

        $rec = $user->recommendations()->first();
        $this->assertNotNull($rec);
        $this->assertSame('recovery', $rec->category);
        $this->assertSame('ai', $rec->source);
        $this->assertSame(0, $rec->priority);
    }

    public function test_save_recommendation_requires_required_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/mcp/recommendation', [
            'date' => '2026-09-01',
        ])->assertStatus(422);

        $this->assertSame(0, Recommendation::count());
    }
}
