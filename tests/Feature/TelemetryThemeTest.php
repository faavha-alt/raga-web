<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Tema UI "Swiss Telemetry Sport" — mengunci agar tidak ada halaman yang
 * kembali memakai palet lama (gray/slate/raga/`dark:`) atau bentuk konsumer
 * (rounded-2xl/3xl, gradient, shadow-glow). Kontrak: docs/DESIGN-CONTRACT.md.
 *
 * Keluhan dikumpulkan dulu supaya satu kali jalan memberi daftar lengkap
 * halaman yang masih menyimpang, bukan hanya yang pertama.
 */
class TelemetryThemeTest extends TestCase
{
    use RefreshDatabase;

    /** Token/kelas tema lama yang tidak boleh muncul lagi di HTML. */
    private const LEGACY_TOKENS = [
        'dark:',
        'rounded-2xl',
        'rounded-3xl',
        'shadow-glow',
        'bg-gradient-to',
        'bg-mesh',
        'text-gradient',
        'raga-accent',
        'raga-primary',
        'raga-energy',
        'raga-excellent',
        'raga-good',
        'raga-moderate',
        'raga-low',
        'bg-gray-',
        'text-gray-',
        'border-gray-',
        'divide-gray-',
        'bg-slate-',
        'text-slate-',
    ];

    public function test_authenticated_pages_render_with_the_telemetry_theme(): void
    {
        $routes = [
            'dashboard', 'feed', 'record.index', 'explore', 'segments.index',
            'segments.create', 'training', 'training.calendar', 'training.load',
            'training.volume', 'training.distribution', 'goals.index', 'running',
            'running.pace', 'running.distance', 'running.records', 'trail',
            'trail.routes', 'health', 'health.heart', 'health.stress',
            'health.body_battery', 'health.daily_metrics', 'recovery', 'analytics',
            'analytics.health_trends', 'analytics.training_trends', 'athlete.dna',
            'ai', 'settings', 'settings.garmin.show', 'settings.ai.show',
            'settings.api-tokens.show', 'profile.edit', 'notifications.index',
            'athletes.relationships', 'activities',
        ];

        $user = User::factory()->create();
        $problems = [];

        foreach ($routes as $route) {
            if (! Route::has($route)) {
                continue;
            }

            $response = $this->actingAs($user)->get(route($route));

            if ($response->getStatusCode() !== 200) {
                $problems[] = "{$route}: HTTP {$response->getStatusCode()}";

                continue;
            }

            foreach (self::legacyTokensIn($response->getContent()) as $token) {
                $problems[] = "{$route} <- {$token}";
            }
        }

        $this->assertSame([], $problems, "Tema lama masih terpakai:\n".implode("\n", $problems));
    }

    public function test_guest_pages_render_with_the_telemetry_theme(): void
    {
        $problems = [];

        foreach (['/', '/login', '/register', '/forgot-password', '/offline'] as $uri) {
            $response = $this->get($uri);

            if ($response->getStatusCode() !== 200) {
                $problems[] = "{$uri}: HTTP {$response->getStatusCode()}";

                continue;
            }

            foreach (self::legacyTokensIn($response->getContent()) as $token) {
                $problems[] = "{$uri} <- {$token}";
            }
        }

        $this->assertSame([], $problems, "Tema lama masih terpakai:\n".implode("\n", $problems));
    }

    /**
     * Pemindai statis: menjaga halaman ber-parameter (detail aktivitas, segment,
     * trail, profil atlet) dan komponen yang tak dirender smoke test di atas.
     */
    public function test_no_blade_source_uses_legacy_theme_tokens(): void
    {
        $problems = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relative = str_replace(resource_path('views').'/', '', $file->getPathname());

            foreach (self::legacyTokensIn($file->getContents()) as $token) {
                $problems[] = "{$relative} <- {$token}";
            }
        }

        $this->assertSame([], $problems, "Tema lama masih terpakai:\n".implode("\n", $problems));
    }

    /** @return list<string> */
    private static function legacyTokensIn(string $html): array
    {
        return array_values(array_filter(
            self::LEGACY_TOKENS,
            static fn (string $token): bool => str_contains($html, $token),
        ));
    }
}
