<?php

namespace Tests\Feature;

use App\Models\GarminConnection;
use App\Models\User;
use App\Services\HealthData\GarminSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Backfill Garmin tanpa jaringan: python di-fake, tapi jumlah chunk, offset,
 * dan hasil backfill()/command tetap diverifikasi.
 */
class GarminBackfillTest extends TestCase
{
    use RefreshDatabase;

    /** JSON minimal supaya `garmin:import` tetap menerima payload. */
    private const PAYLOAD = '{"activities":[],"daily":[]}';

    /** @var list<array<int, string>> */
    private array $pythonCalls = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->pythonCalls = [];
    }

    /** Fake proses python: catat command, balas JSON minimal. */
    private function fakePython(?callable $failOn = null): void
    {
        Process::fake(function ($process) use ($failOn) {
            if (in_array('scripts/garmin_sync.py', $process->command, true)) {
                $this->pythonCalls[] = $process->command;

                if ($failOn && $failOn(count($this->pythonCalls))) {
                    // exit code adalah argumen KETIGA — selalu pakai named argument.
                    return Process::result(output: '', errorOutput: 'boom', exitCode: 1);
                }
            }

            return Process::result(output: self::PAYLOAD);
        });
    }

    private function user(): User
    {
        $user = User::factory()->create();
        GarminConnection::create(['user_id' => $user->id, 'connected_at' => now()]);

        return $user;
    }

    /** @param array<int, string> $command */
    private function optionValue(array $command, string $name): int
    {
        return (int) $command[array_search($name, $command, true) + 1];
    }

    public function test_backfill_chunks_days_newest_first_with_increasing_offset(): void
    {
        $user = $this->user();
        $this->fakePython();
        $progress = [];

        $result = app(GarminSyncService::class)->backfill($user, 150, 60, function (array $info) use (&$progress): void {
            $progress[] = $info;
        });

        $this->assertSame('success', $result['status']);
        $this->assertSame(150, $result['days']);
        $this->assertSame(3, $result['chunks']);
        $this->assertNotNull($result['import_output']);

        $this->assertCount(3, $this->pythonCalls);
        $this->assertSame([0, 60, 120], array_map(fn ($c) => $this->optionValue($c, '--offset'), $this->pythonCalls));
        // Chunk terakhir dipangkas agar totalnya tetap 150 hari.
        $this->assertSame([60, 60, 30], array_map(fn ($c) => $this->optionValue($c, '--days'), $this->pythonCalls));

        // Progres dipanggil sekali per chunk sebelum chunk dijalankan.
        $this->assertSame([1, 2, 3], array_column($progress, 'chunk'));
        $this->assertSame([0, 60, 120], array_column($progress, 'offset'));

        $connection = $user->garminConnection->refresh();
        $this->assertSame('success', $connection->last_sync_status);
        $this->assertNotNull($connection->last_synced_at);
        $this->assertNull($connection->last_sync_message);
    }

    public function test_backfill_stops_on_failed_chunk_and_keeps_previous_progress(): void
    {
        $user = $this->user();
        $this->fakePython(failOn: fn (int $n): bool => $n === 2);

        $result = app(GarminSyncService::class)->backfill($user, 150, 60);

        $this->assertSame('error', $result['status']);
        $this->assertSame(150, $result['days']);
        $this->assertSame(1, $result['chunks']);
        $this->assertSame('boom', $result['message']);
        $this->assertCount(2, $this->pythonCalls);

        $connection = $user->garminConnection->refresh();
        $this->assertSame('error', $connection->last_sync_status);
        $this->assertSame('boom', $connection->last_sync_message);
    }

    public function test_command_runs_one_python_process_per_chunk(): void
    {
        $user = $this->user();
        $this->fakePython();

        $exitCode = Artisan::call('garmin:sync', ['--user' => $user->id, '--days' => 150, '--chunk' => 60]);

        $this->assertSame(0, $exitCode);
        $this->assertCount(3, $this->pythonCalls);
        $this->assertSame([0, 60, 120], array_map(fn ($c) => $this->optionValue($c, '--offset'), $this->pythonCalls));
    }

    public function test_command_uses_plain_sync_for_short_ranges(): void
    {
        $user = $this->user();
        $this->fakePython();

        $exitCode = Artisan::call('garmin:sync', ['--user' => $user->id, '--days' => 2]);

        $this->assertSame(0, $exitCode);
        $this->assertCount(1, $this->pythonCalls);
        $this->assertNotContains('--offset', $this->pythonCalls[0]);
    }
}
