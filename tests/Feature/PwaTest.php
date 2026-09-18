<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Aset PWA.
 *
 * Sebagian besar aset (sw.js, ikon) adalah berkas statis di public/ yang
 * disajikan langsung oleh nginx, di luar framework — karena itu test ini
 * memeriksa keberadaan dan isi berkas aslinya.
 *
 * Manifest adalah pengecualian: ia disajikan lewat rute aplikasi supaya tipe
 * MIME-nya benar (lihat App\Support\WebManifest).
 */
class PwaTest extends TestCase
{
    private function contents(string $relativeToBasePath): string
    {
        $path = base_path($relativeToBasePath);

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_manifest_route_serves_json_content_type_and_required_fields(): void
    {
        $response = $this->get('/manifest.webmanifest');

        $response->assertOk();

        // Browser menolak manifest yang tidak ber-tipe MIME JSON. nginx mengirim
        // `application/octet-stream` untuk ekstensi tak dikenal, jadi manifest
        // harus datang dari rute aplikasi dan bukan berkas statis.
        $this->assertStringContainsString(
            'application/manifest+json',
            (string) $response->headers->get('Content-Type'),
        );

        $manifest = $response->json();

        $this->assertIsArray($manifest);
        $this->assertSame('RAGA', $manifest['short_name']);
        $this->assertSame('/dashboard', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('id', $manifest['lang']);
        $this->assertSame('#090d16', $manifest['theme_color']);
        $this->assertSame('#090d16', $manifest['background_color']);
        $this->assertNotEmpty($manifest['name']);
        $this->assertNotEmpty($manifest['description']);
    }

    public function test_no_static_manifest_file_shadows_the_route(): void
    {
        // Bila berkas ini ada, nginx menyajikannya lebih dulu daripada rute dan
        // tipe MIME manifest kembali salah.
        $this->assertFileDoesNotExist(public_path('manifest.webmanifest'));
    }

    public function test_manifest_declares_installable_icon_set_that_exists_on_disk(): void
    {
        $manifest = $this->get('/manifest.webmanifest')->json();

        $sizes = [];
        $maskable = [];

        foreach ($manifest['icons'] as $icon) {
            $this->assertStringStartsWith('/icons/', $icon['src']);

            $sizes[] = $icon['sizes'];
            if (($icon['purpose'] ?? '') === 'maskable') {
                $maskable[] = $icon['src'];
            }

            // Setiap ikon yang dijanjikan manifest harus benar-benar ada.
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }

        // Chrome butuh 192px dan 512px agar aplikasi bisa dipasang.
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
        $this->assertNotEmpty($maskable, 'Manifest harus menyediakan ikon maskable.');
    }

    public function test_png_icons_are_real_png_files(): void
    {
        foreach (['icons/icon-192x192.png', 'icons/icon-512x512.png', 'icons/icon-maskable-512x512.png'] as $relative) {
            $bytes = $this->contents('public/'.$relative);

            // Tanda tangan berkas PNG: \x89PNG\r\n\x1a\n
            $this->assertSame("\x89PNG\r\n\x1a\n", substr($bytes, 0, 8), "$relative bukan berkas PNG yang sah.");
        }
    }

    public function test_vector_icon_is_a_square_svg_in_the_raga_palette(): void
    {
        $icon = $this->contents('public/icons/icon.svg');

        $this->assertStringStartsWith('<svg', trim($icon));
        $this->assertStringContainsString('xmlns="http://www.w3.org/2000/svg"', $icon);
        $this->assertStringContainsString('viewBox="0 0 512 512"', $icon);
        $this->assertStringContainsString('#21A08C', $icon);
        $this->assertStringContainsString('#6C5CE7', $icon);
    }

    public function test_service_worker_has_versioned_cache_and_offline_fallback(): void
    {
        $sw = $this->contents('public/sw.js');

        $this->assertStringContainsString("const VERSION = 'raga-static-v1'", $sw);
        $this->assertStringContainsString("const OFFLINE_URL = '/offline'", $sw);
        $this->assertStringContainsString("url.pathname.startsWith('/build/')", $sw);
        $this->assertStringContainsString('caches.keys()', $sw);
        $this->assertStringContainsString('self.skipWaiting()', $sw);
        $this->assertStringContainsString('self.clients.claim()', $sw);
        $this->assertStringContainsString("request.mode === 'navigate'", $sw);
    }

    /**
     * Penjaga privasi: service worker tidak boleh menyimpan apa pun yang
     * spesifik per pengguna. Bila HTML atau API ikut ter-cache, pengguna
     * berikutnya di perangkat yang sama bisa melihat data orang sebelumnya.
     */
    public function test_service_worker_never_caches_html_or_api_responses(): void
    {
        $sw = $this->contents('public/sw.js');

        // API tidak pernah ditangani service worker.
        $this->assertStringContainsString("url.pathname.startsWith('/api/')", $sw);

        // Hanya metode GET yang ditangani.
        $this->assertStringContainsString("request.method !== 'GET'", $sw);

        // Daftar precache hanya berisi aset statis — tidak ada dokumen HTML ("/").
        $this->assertMatchesRegularExpression('/const PRECACHE_URLS = \[(.*?)\];/s', $sw, 'PRECACHE_URLS tidak ditemukan.');
        preg_match('/const PRECACHE_URLS = \[(.*?)\];/s', $sw, $matches);
        $this->assertStringNotContainsString("'/'", $matches[1], 'Dokumen HTML tidak boleh di-precache.');
        $this->assertStringContainsString('OFFLINE_URL', $matches[1]);
    }

    public function test_offline_page_is_standalone_without_the_authenticated_shell(): void
    {
        $offline = $this->contents('resources/views/offline.blade.php');

        $this->assertStringContainsString('<!DOCTYPE html>', $offline);
        $this->assertStringContainsString('Kamu sedang offline', $offline);
        $this->assertStringNotContainsString('<x-app-layout', $offline);
    }

    public function test_pwa_partial_registers_service_worker_and_install_prompt(): void
    {
        $partial = $this->contents('resources/views/partials/pwa.blade.php');

        $this->assertStringContainsString("navigator.serviceWorker.register('/sw.js')", $partial);
        $this->assertStringContainsString('beforeinstallprompt', $partial);
        $this->assertStringContainsString('Pasang aplikasi', $partial);

        // Tag <head> PWA sengaja ditulis di layout, bukan di sini, supaya hanya
        // ada satu sumber kebenaran (dan tidak ada manifest ganda).
        $this->assertStringNotContainsString('<link rel="manifest"', $partial);
        $this->assertStringNotContainsString("asset('manifest.webmanifest')", $partial);
    }

    public function test_pwa_partial_shows_an_ios_install_hint_that_is_dismissible(): void
    {
        $partial = $this->contents('resources/views/partials/pwa.blade.php');

        // Safari iOS tidak mendukung beforeinstallprompt, jadi panduan manual
        // ini yang membuat pengguna iPhone/iPad tahu cara memasang aplikasi.
        $this->assertStringContainsString('Alpine.data(\'ragaIosInstallHint\'', $partial);
        $this->assertStringContainsString('Ketuk tombol Bagikan di Safari, lalu pilih Tambahkan ke Layar Utama.', $partial);

        // Hanya muncul di iOS yang belum terpasang.
        $this->assertStringContainsString('maxTouchPoints', $partial);
        $this->assertStringContainsString("display-mode: standalone", $partial);
        $this->assertStringContainsString('navigator.standalone', $partial);

        // Preferensi tutup disimpan di localStorage.
        $this->assertStringContainsString('raga:ios-install-hint-dismissed', $partial);
        $this->assertStringContainsString('localStorage.setItem', $partial);
    }

    public function test_apple_touch_icon_is_a_180_px_square_png(): void
    {
        $path = public_path('icons/apple-touch-icon.png');

        $bytes = $this->contents('public/icons/apple-touch-icon.png');

        // Tanda tangan berkas PNG: \x89PNG\r\n\x1a\n
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($bytes, 0, 8), 'apple-touch-icon.png bukan berkas PNG yang sah.');

        // iOS memakai berkas ini apa adanya; ukuran 180x180 adalah yang
        // direkomendasikan Apple untuk iPhone ber-Retina.
        $size = getimagesize($path);
        $this->assertIsArray($size);
        $this->assertSame(180, $size[0]);
        $this->assertSame(180, $size[1]);
    }

    public function test_all_layouts_reference_the_apple_touch_icon(): void
    {
        foreach ([
            'resources/views/layouts/app.blade.php',
            'resources/views/layouts/guest.blade.php',
            'resources/views/welcome.blade.php',
        ] as $relative) {
            $this->assertStringContainsString(
                'rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon.png"',
                $this->contents($relative),
                "$relative belum menunjuk ikon apple-touch 180x180.",
            );
        }
    }

    public function test_layout_declares_pwa_head_tags_once(): void
    {
        $layout = $this->contents('resources/views/layouts/app.blade.php');

        $this->assertStringContainsString('<link rel="manifest" href="/manifest.webmanifest">', $layout);
        $this->assertStringContainsString('name="theme-color"', $layout);
        $this->assertStringContainsString('rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon.png"', $layout);
        $this->assertStringContainsString("@include('partials.pwa')", $layout);

        // iOS tidak mendukung SVG untuk apple-touch-icon — harus PNG.
        $this->assertStringNotContainsString('apple-touch-icon" href="/icons/icon.svg"', $layout);

        // Manifest hanya dideklarasikan sekali di layout.
        $this->assertSame(1, substr_count($layout, 'rel="manifest"'));
    }
}
