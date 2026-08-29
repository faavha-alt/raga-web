<?php

namespace Tests\Feature;

use App\Models\GarminConnection;
use App\Models\User;
use App\Services\HealthData\GarminSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class McpGarminSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_endpoint_requires_authentication(): void
    {
        $this->postJson('/api/mcp/sync-garmin')->assertUnauthorized();
    }

    public function test_sync_returns_error_when_garmin_not_connected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->mock(GarminSyncService::class)->shouldNotReceive('syncForUser');

        $this->postJson('/api/mcp/sync-garmin')
            ->assertOk()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'This RAGA account is not connected to Garmin. Connect it in Settings → Garmin in the RAGA web app first.');
    }

    public function test_sync_rejects_out_of_range_days(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        GarminConnection::create(['user_id' => $user->id, 'connected_at' => now()]);

        $this->postJson('/api/mcp/sync-garmin', ['days' => 30])->assertStatus(422);
    }

    public function test_sync_delegates_to_service_with_requested_days_and_shapes_response(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        GarminConnection::create(['user_id' => $user->id, 'connected_at' => now()]);

        $this->mock(GarminSyncService::class, function (MockInterface $mock) use ($user) {
            $mock->shouldReceive('syncForUser')
                ->once()
                ->withArgs(fn (User $u, int $days) => $u->is($user) && $days === 3)
                ->andReturn(['status' => 'success', 'days' => 3, 'message' => null, 'import_output' => 'ok']);
        });

        $this->postJson('/api/mcp/sync-garmin', ['days' => 3])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('synced_days', 3)
            ->assertJsonPath('message', 'Garmin sync complete; recovery recomputed.')
            ->assertJsonPath('import_output', 'ok');
    }

    public function test_service_runs_pull_imports_and_marks_connection_synced(): void
    {
        $user = User::factory()->create();
        $connection = GarminConnection::create(['user_id' => $user->id, 'connected_at' => now()]);

        Process::fake(['*garmin_sync.py*' => Process::result(output: '{}')]);

        $result = app(GarminSyncService::class)->syncForUser($user, 2);

        $this->assertSame('success', $result['status']);
        Process::assertRan(fn ($process) => in_array('scripts/garmin_sync.py', $process->command, true)
            && in_array('2', $process->command, true));

        $connection->refresh();
        $this->assertSame('success', $connection->last_sync_status);
        $this->assertNotNull($connection->last_synced_at);
        $this->assertNull($connection->last_sync_message);
    }

    public function test_service_records_failure_when_pull_fails(): void
    {
        $user = User::factory()->create();
        $connection = GarminConnection::create(['user_id' => $user->id, 'connected_at' => now()]);

        Process::fake([
            '*garmin_sync.py*' => Process::result(output: '', errorOutput: 'garmin: login token expired', exitCode: 1),
        ]);

        $result = app(GarminSyncService::class)->syncForUser($user);

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('login token expired', (string) $result['message']);

        $connection->refresh();
        $this->assertSame('error', $connection->last_sync_status);
        $this->assertStringContainsString('login token expired', (string) $connection->last_sync_message);
    }

    public function test_service_returns_error_when_not_connected_without_running_anything(): void
    {
        $user = User::factory()->create();

        Process::fake();

        $result = app(GarminSyncService::class)->syncForUser($user);

        $this->assertSame('error', $result['status']);
        Process::assertNothingRan();
    }
}
