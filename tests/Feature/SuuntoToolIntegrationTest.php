<?php

namespace Tests\Feature;

use App\Models\SleepSession;
use App\Models\SuuntoConnection;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSample;
use App\Services\Suunto\SuuntoSyncService;
use App\Services\Suunto\SuuntoToolClient;
use App\Services\Suunto\SuuntoToolMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Jalur tidak resmi Suunto lewat CLI `suuntool` (backend aplikasi Suunto):
 * login email/password, penyimpanan sesi per user, dan pemilihan driver sync.
 * Semua proses eksternal dipalsukan — tidak ada binary/akun Suunto di CI.
 */
class SuuntoToolIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.suunto.binary' => 'suuntool',
            'services.suunto.sync_limit' => 20,
        ]);

        $this->resetBinaryAvailabilityCache();

        // Sesi suuntool hidup di berkas (di luar database), jadi harus
        // dibersihkan manual supaya test tidak saling mewarisi sesi.
        File::deleteDirectory(storage_path('app/suunto_sessions'));
    }

    public function test_settings_page_offers_tool_login_when_the_binary_is_available(): void
    {
        Process::fake(['*which*' => Process::result("/usr/local/bin/suuntool\n")]);

        $this->actingAs(User::factory()->create())
            ->get(route('settings.suunto.show'))
            ->assertOk()
            ->assertSee('Password Suunto')
            ->assertSee('Hubungkan');
    }

    public function test_settings_page_explains_how_to_install_the_binary_when_missing(): void
    {
        Process::fake(['*which*' => Process::result(output: '', errorOutput: 'not found', exitCode: 1)]);

        $this->actingAs(User::factory()->create())
            ->get(route('settings.suunto.show'))
            ->assertOk()
            ->assertSee('belum terpasang di server')
            ->assertSee('github.com/tajchert/suuntool', false);
    }

    public function test_tool_login_stores_a_password_mode_connection_without_tokens(): void
    {
        Process::fake([
            '*which*' => Process::result("/usr/local/bin/suuntool\n"),
            '*login*' => Process::result("Logged in as alice.\n"),
            '*whoami*' => Process::result('{"username":"alice","email":"alice@example.com"}'),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('settings.suunto.login'), [
                'email' => 'alice@example.com',
                'password' => 'secret-password',
            ])
            ->assertRedirect(route('settings.suunto.show'))
            ->assertSessionHas('status');

        $connection = $user->fresh()->suuntoConnection;

        $this->assertNotNull($connection);
        $this->assertSame('password', $connection->auth_mode);
        $this->assertSame('alice@example.com', $connection->email);
        $this->assertSame('alice', $connection->suunto_username);
        $this->assertNull($connection->access_token);
        $this->assertNull($connection->refresh_token);

        // Password hanya lewat stdin, tidak pernah masuk ke argumen proses.
        Process::assertRan(function (PendingProcess $process) {
            $command = is_array($process->command) ? implode(' ', $process->command) : (string) $process->command;

            return str_starts_with($command, 'suuntool login')
                && ! str_contains($command, 'secret-password');
        });
    }

    public function test_tool_login_surfaces_the_error_message(): void
    {
        Process::fake([
            '*which*' => Process::result("/usr/local/bin/suuntool\n"),
            '*login*' => Process::result(
                output: (string) json_encode(['error' => ['code' => 'AUTH', 'message' => 'Invalid credentials', 'hint' => 'Check your password']]),
                errorOutput: '',
                exitCode: 4,
            ),
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('settings.suunto.login'), [
                'email' => 'alice@example.com',
                'password' => 'wrong',
            ])
            ->assertRedirect(route('settings.suunto.show'))
            ->assertSessionHasErrors('suunto');

        $this->assertNull(SuuntoConnection::first());
    }

    public function test_sync_reports_a_missing_binary_instead_of_failing_silently(): void
    {
        Process::fake(['*which*' => Process::result(output: '', errorOutput: 'not found', exitCode: 1)]);

        $user = $this->passwordConnectedUser();

        $result = app(SuuntoSyncService::class)->syncForUser($user, 7);

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('tidak ditemukan di server', (string) $result['message']);
        $this->assertSame('error', $user->fresh()->suuntoConnection->last_sync_status);
    }

    public function test_sync_refuses_when_the_session_file_is_gone(): void
    {
        Process::fake(['*which*' => Process::result("/usr/local/bin/suuntool\n")]);

        $user = $this->passwordConnectedUser();

        $result = app(SuuntoSyncService::class)->syncForUser($user, 7);

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('Sesi Suunto belum ada', (string) $result['message']);
    }

    public function test_disconnect_removes_the_connection(): void
    {
        $user = $this->passwordConnectedUser();

        $this->actingAs($user)
            ->post(route('settings.suunto.disconnect'))
            ->assertRedirect(route('settings.suunto.show'));

        $this->assertNull($user->fresh()->suuntoConnection);
    }

    public function test_sync_imports_workouts_samples_and_sleep_via_tool(): void
    {
        Process::fake([
            '*which*' => Process::result("/usr/local/bin/suuntool\n"),
            '*sml*' => Process::result((string) json_encode($this->smlPayload())),
            '*sleep*' => Process::result($this->sleepNdjson()),
            '*workouts*' => Process::result((string) json_encode([
                'error' => null,
                'payload' => [$this->workoutPayload()],
                'metadata' => ['until' => 1757703600000],
            ])),
        ]);

        $user = $this->passwordConnectedUser();
        file_put_contents(SuuntoToolClient::sessionPathForUser($user), '{"session":"stub"}');

        $result = app(SuuntoSyncService::class)->syncForUser($user, 7);

        $this->assertSame('success', $result['status'], (string) $result['message']);
        $this->assertSame(1, $result['imported']);

        $workout = Workout::where('user_id', $user->id)->where('source', 'suunto')->firstOrFail();

        $this->assertSame('running', $workout->type);
        $this->assertSame(10000.0, (float) $workout->distance_meters);
        $this->assertSame(600, (int) $workout->active_calories);
        $this->assertSame(145, (int) $workout->average_heart_rate);

        $this->assertGreaterThan(0, WorkoutSample::where('workout_id', $workout->id)->whereNotNull('heart_rate')->count());

        $sleep = SleepSession::where('user_id', $user->id)->where('source', 'suunto')->first();
        $this->assertNotNull($sleep, 'Tidur tidak terimpor dari wellness sleep.');
        $this->assertSame(80, (int) $sleep->sleep_score);
        $this->assertSame(90.0, (float) $sleep->deep_minutes);
    }

    public function test_sync_skips_workouts_already_imported_without_downloading_samples(): void
    {
        Process::fake([
            '*which*' => Process::result("/usr/local/bin/suuntool\n"),
            '*sml*' => Process::result((string) json_encode($this->smlPayload())),
            '*sleep*' => Process::result(''),
            '*workouts*' => Process::result((string) json_encode([$this->workoutPayload()])),
        ]);

        $user = $this->passwordConnectedUser();
        file_put_contents(SuuntoToolClient::sessionPathForUser($user), '{"session":"stub"}');

        // Sudah pernah diimpor: waktu mulai sama & source suunto.
        $mapped = app(SuuntoToolMapper::class)->toGarminActivity($this->workoutPayload());

        Workout::create([
            'user_id' => $user->id,
            'type' => 'running',
            'start_date' => $mapped['startTimeLocal'],
            'end_date' => $mapped['startTimeLocal'],
            'source' => 'suunto',
        ]);

        $result = app(SuuntoSyncService::class)->syncForUser($user, 7);

        $this->assertSame('success', $result['status'], (string) $result['message']);
        $this->assertSame(0, $result['imported']);
        $this->assertSame(1, $result['skipped']);

        Process::assertNotRan(function (PendingProcess $process) {
            $command = is_array($process->command) ? implode(' ', $process->command) : (string) $process->command;

            return str_contains($command, 'sml');
        });
    }

    private function passwordConnectedUser(): User
    {
        $user = User::factory()->create();

        SuuntoConnection::create([
            'user_id' => $user->id,
            'auth_mode' => 'password',
            'email' => 'alice@example.com',
            'suunto_username' => 'alice',
            'connected_at' => now(),
        ]);

        return $user->fresh();
    }

    /** Cache statis `isAvailable()` harus dibersihkan antar test. */
    private function resetBinaryAvailabilityCache(): void
    {
        $property = (new \ReflectionClass(SuuntoToolClient::class))->getProperty('availability');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }

    /**
     * Satu workout seperti pada payload `/v1/workouts` suuntool
     * (`startTime` epoch ms, `totalTime` detik, `totalDistance` meter).
     *
     * @return array<string, mixed>
     */
    private function workoutPayload(): array
    {
        return [
            'key' => 'wk_abc123',
            'activityId' => 1,
            'startTime' => 1757700000000,
            'stopTime' => 1757703600000,
            'totalTime' => 3600,
            'totalDistance' => 10000,
            'totalAscent' => 120,
            'totalDescent' => 110,
            'energyConsumption' => 600,
            'hrdata' => ['avg' => 145, 'workoutMaxHR' => 172],
        ];
    }

    /**
     * Dua sampel SML bentuk asli (`Data.Samples[].Attributes."suunto/sml".Sample`).
     *
     * @return array<string, mixed>
     */
    private function smlPayload(): array
    {
        return [
            'Data' => [
                'Samples' => [
                    [
                        'TimeISO8601' => '2025-09-12T18:40:00Z',
                        'Attributes' => ['suunto/sml' => ['Sample' => [
                            'HR' => 140,
                            'GPSAltitude' => 100,
                            'Latitude' => -7.5,
                            'Longitude' => 110.8,
                            'UTC' => 1757700000000,
                        ]]],
                    ],
                    [
                        'TimeISO8601' => '2025-09-12T18:40:01Z',
                        'Attributes' => ['suunto/sml' => ['Sample' => [
                            'HR' => 150,
                            'GPSAltitude' => 101,
                            'Latitude' => -7.5001,
                            'Longitude' => 110.8001,
                            'UTC' => 1757700001000,
                        ]]],
                    ],
                ],
            ],
        ];
    }

    /** Satu baris NDJSON `wellness sleep` (durasi dalam detik, quality 0..1). */
    private function sleepNdjson(): string
    {
        return json_encode([
            'timestamp' => '2025-09-12T15:30:00Z',
            'entryData' => [
                'duration' => 28800,
                'deepSleepDuration' => 5400,
                'lightSleepDuration' => 18000,
                'remSleepDuration' => 3600,
                'hrAvg' => 1.1,
                'quality' => 0.8,
                'isNap' => false,
                'sleepId' => 12345,
            ],
        ])."\n";
    }
}
