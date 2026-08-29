<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\HealthData\GarminSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GarminSyncLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_returns_busy_when_a_sync_is_already_running(): void
    {
        $user = User::factory()->create();

        // Simulate another request already syncing this user.
        $lock = Cache::lock('garmin-sync:user:'.$user->id, 300);
        $this->assertTrue($lock->get());

        try {
            $result = app(GarminSyncService::class)->syncForUser($user);

            $this->assertSame('error', $result['status']);
            $this->assertStringContainsString('sedang berjalan', $result['message']);
        } finally {
            $lock->release();
        }
    }

    public function test_sync_proceeds_when_lock_is_free(): void
    {
        $user = User::factory()->create();

        // Lock is free and the user has no Garmin connection, so sync should
        // proceed past the lock check and fail on the missing connection — not
        // on the lock.
        $result = app(GarminSyncService::class)->syncForUser($user);

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('Belum terhubung', $result['message']);
    }
}
