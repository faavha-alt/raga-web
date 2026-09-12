<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Buat notifikasi langsung di tabel (tanpa kelas App\Notifications konkret),
     * supaya test ini tidak bergantung pada notifikasi yang ditulis agen lain.
     */
    private function notify(User $user, array $data = [], ?Carbon $readAt = null, ?Carbon $createdAt = null): DatabaseNotification
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\NewFollower',
            'data' => $data,
            'read_at' => $readAt,
            'created_at' => $createdAt ?? Carbon::now(),
            'updated_at' => $createdAt ?? Carbon::now(),
        ]);
    }

    public function test_guest_is_redirected_to_login_from_notifications_index(): void
    {
        $this->get('/notifications')->assertRedirect(route('login'));
    }

    public function test_index_lists_only_the_viewers_own_notifications(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();

        $this->notify($viewer, ['message' => 'Pesan milik viewer.', 'actor_name' => 'Andi']);
        $this->notify($other, ['message' => 'Pesan milik orang lain.', 'actor_name' => 'Budi']);

        $this->actingAs($viewer)
            ->get('/notifications')
            ->assertOk()
            ->assertSee('Pesan milik viewer.')
            ->assertDontSee('Pesan milik orang lain.');
    }

    public function test_unread_notification_is_shown_as_unread_and_renders_message(): void
    {
        $user = User::factory()->create();
        $this->notify($user, [
            'message' => 'Andi mulai mengikuti kamu.',
            'url' => '/feed',
            'actor_id' => 7,
            'actor_name' => 'Andi Pratama',
        ]);

        $response = $this->actingAs($user)->get('/notifications')->assertOk();

        $response->assertSee('Andi mulai mengikuti kamu.');
        $response->assertSee('data-unread="true"', false);
        $response->assertSee('Baru');
        $response->assertSee('AP');
    }

    public function test_owner_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = $this->notify($user, ['message' => 'Test baca.', 'url' => '/feed']);

        $this->actingAs($user)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect('/feed');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_non_owner_cannot_mark_notification_as_read(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $notification = $this->notify($owner, ['message' => 'Rahasia.', 'url' => '/feed']);

        $this->actingAs($intruder)
            ->post(route('notifications.read', $notification->id))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_read_only_affects_the_viewers_notifications(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();

        $first = $this->notify($viewer, ['message' => 'Satu.', 'url' => '/feed']);
        $second = $this->notify($viewer, ['message' => 'Dua.', 'url' => '/feed']);
        $otherNotification = $this->notify($other, ['message' => 'Punya orang lain.', 'url' => '/feed']);

        $this->actingAs($viewer)
            ->post(route('notifications.readAll'))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('status', 'Semua notifikasi ditandai sudah dibaca.');

        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNotNull($second->fresh()->read_at);
        $this->assertNull($otherNotification->fresh()->read_at);
    }

    public function test_notification_bell_renders_the_unread_count(): void
    {
        $user = User::factory()->create();
        $this->notify($user, ['message' => 'Satu.', 'url' => '/feed']);
        $this->notify($user, ['message' => 'Dua.', 'url' => '/feed']);
        $this->notify($user, ['message' => 'Sudah dibaca.', 'url' => '/feed'], Carbon::now());

        $this->actingAs($user)
            ->blade('<x-notification-bell />')
            ->assertSee('>2<', false)
            ->assertSee(route('notifications.index'));
    }

    public function test_notification_missing_optional_data_keys_renders_without_error(): void
    {
        $user = User::factory()->create();
        $this->notify($user, ['message' => 'Pesan tanpa data tambahan.']);
        $this->notify($user, []);

        $this->actingAs($user)
            ->get('/notifications')
            ->assertOk()
            ->assertSee('Pesan tanpa data tambahan.')
            ->assertSee('Ada aktivitas baru di RAGA.')
            ->assertSee('Pengguna RAGA');
    }

    public function test_index_paginates_twenty_five_notifications_per_page(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 26; $i++) {
            $this->notify(
                $user,
                ['message' => 'Pesan ke-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'url' => '/feed'],
                null,
                Carbon::now()->subMinutes(26 - $i),
            );
        }

        $this->actingAs($user)
            ->get('/notifications')
            ->assertOk()
            ->assertSee('Pesan ke-26')
            ->assertDontSee('Pesan ke-01');

        $this->actingAs($user)
            ->get('/notifications?page=2')
            ->assertOk()
            ->assertSee('Pesan ke-01');
    }
}
