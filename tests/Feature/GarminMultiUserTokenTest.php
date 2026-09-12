<?php

namespace Tests\Feature;

use App\Models\GarminConnection;
use App\Models\User;
use App\Services\HealthData\GarminSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class GarminMultiUserTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $baseTokens = storage_path('app/garmin_tokens');
        if (File::isDirectory($baseTokens)) {
            File::deleteDirectory($baseTokens);
        }

        parent::tearDown();
    }

    public function test_connect_passes_user_scoped_token_store_to_login_script(): void
    {
        $user = User::factory()->create();
        $expectedTokenPath = storage_path('app/garmin_tokens/'.$user->id);

        Process::fake([
            '*garmin_login.py*' => Process::result(json_encode(['status' => 'ok'])),
        ]);

        $response = $this->actingAs($user)->post(route('settings.garmin.connect'), [
            'email' => 'runner@example.com',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('settings.garmin.show'));
        $this->assertDatabaseHas('garmin_connections', ['user_id' => $user->id]);

        Process::assertRan(function ($process) use ($expectedTokenPath) {
            $input = json_decode($process->input, true);
            return is_array($input)
                && ($input['token_store'] ?? null) === $expectedTokenPath
                && ($input['email'] ?? null) === 'runner@example.com';
        });
    }

    public function test_disconnect_deletes_only_current_users_token_store(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        GarminConnection::create(['user_id' => $user1->id, 'connected_at' => now()]);
        GarminConnection::create(['user_id' => $user2->id, 'connected_at' => now()]);

        $user1TokenDir = storage_path('app/garmin_tokens/'.$user1->id);
        $user2TokenDir = storage_path('app/garmin_tokens/'.$user2->id);

        File::makeDirectory($user1TokenDir, 0700, true, true);
        File::put($user1TokenDir.'/token.json', '{"token": "user1"}');

        File::makeDirectory($user2TokenDir, 0700, true, true);
        File::put($user2TokenDir.'/token.json', '{"token": "user2"}');

        $this->assertTrue(File::isDirectory($user1TokenDir));
        $this->assertTrue(File::isDirectory($user2TokenDir));

        $response = $this->actingAs($user1)->post(route('settings.garmin.disconnect'));
        $response->assertRedirect(route('settings.garmin.show'));

        $this->assertDatabaseMissing('garmin_connections', ['user_id' => $user1->id]);
        $this->assertDatabaseHas('garmin_connections', ['user_id' => $user2->id]);

        $this->assertFalse(File::isDirectory($user1TokenDir));
        $this->assertTrue(File::isDirectory($user2TokenDir));
        $this->assertTrue(File::exists($user2TokenDir.'/token.json'));
    }

    public function test_sync_passes_token_store_argument_to_python_sync_script(): void
    {
        $user = User::factory()->create();
        GarminConnection::create(['user_id' => $user->id, 'connected_at' => now()]);
        $expectedTokenPath = storage_path('app/garmin_tokens/'.$user->id);

        Process::fake([
            '*garmin_sync.py*' => Process::result(output: json_encode([
                'generated_at' => now()->toDateString(),
                'daily' => [],
                'activities' => [],
            ])),
        ]);

        $service = app(GarminSyncService::class);
        $result = $service->syncForUser($user, 3);

        $this->assertSame('success', $result['status']);

        Process::assertRan(function ($process) use ($expectedTokenPath) {
            return in_array('scripts/garmin_sync.py', $process->command, true)
                && in_array('--token-store', $process->command, true)
                && in_array($expectedTokenPath, $process->command, true)
                && in_array('3', $process->command, true);
        });
    }
}
