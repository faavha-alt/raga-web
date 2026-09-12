<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Shell navigasi (top nav) — menjaga agar seluruh modul tetap terjangkau
 * setelah layout sidebar diganti top navigation.
 */
class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_navigation_replaces_the_sidebar_and_links_every_module(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();

        // Sidebar lama harus benar-benar hilang, bukan disembunyikan CSS.
        $response->assertDontSee('</aside>', false);
        $response->assertDontSee('sidebarOpen', false);

        // Shell baru + strip sekunder.
        $response->assertSee('mobileOpen', false);
        $response->assertSee('RAGA // Telemetry Suite');

        // Setiap modul tetap punya tautan di halaman.
        foreach ([
            'dashboard', 'feed', 'training', 'health', 'analytics', 'ai',
            'explore', 'segments.index', 'goals.index', 'running', 'trail',
            'recovery', 'settings', 'record.index',
        ] as $route) {
            $response->assertSee(route($route), false);
        }
    }

    public function test_avatar_menu_exposes_profile_and_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('profile.edit'), false)
            ->assertSee(route('logout'), false);
    }

    public function test_every_navigation_route_is_still_registered(): void
    {
        foreach ([
            'dashboard', 'feed', 'record.index', 'explore', 'segments.index',
            'training', 'goals.index', 'running', 'trail', 'health', 'recovery',
            'analytics', 'ai', 'settings', 'profile.edit', 'logout',
        ] as $route) {
            $this->assertTrue(
                Route::has($route),
                "Rute navigasi [{$route}] hilang.",
            );
        }
    }
}
