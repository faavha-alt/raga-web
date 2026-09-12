<?php

namespace App\Support;

/**
 * Web App Manifest RAGA.
 *
 * Manifest disajikan lewat rute aplikasi, bukan sebagai berkas statis di
 * `public/`, karena nginx tidak mengenal ekstensi `.webmanifest` dan mengirimnya
 * sebagai `application/octet-stream`. Browser mensyaratkan tipe MIME JSON untuk
 * manifest, sehingga berkas statis akan ditolak dan PWA tidak bisa dipasang.
 * Menyajikannya dari aplikasi membuat tipe MIME benar di server mana pun,
 * termasuk `php artisan serve`, tanpa perlu menyentuh konfigurasi nginx.
 */
class WebManifest
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(): array
    {
        return [
            'name' => 'RAGA - Health & Training Tracker',
            'short_name' => 'RAGA',
            'description' => 'Pelacak kesehatan dan latihan (lari & trail) berbasis Garmin Connect: recovery, training load, personal record, segment, dan AI Coach.',
            'id' => '/',
            'start_url' => '/dashboard',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait-primary',
            'background_color' => '#090d16',
            'theme_color' => '#090d16',
            'lang' => 'id',
            'dir' => 'ltr',
            'categories' => ['health', 'fitness', 'sports', 'lifestyle'],
            'icons' => [
                [
                    'src' => '/icons/icon-192x192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/icons/icon-512x512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/icons/icon-maskable-512x512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
                [
                    'src' => '/icons/icon.svg',
                    'sizes' => 'any',
                    'type' => 'image/svg+xml',
                    'purpose' => 'any',
                ],
            ],
            'shortcuts' => [
                [
                    'name' => 'Mulai rekam',
                    'short_name' => 'Rekam',
                    'url' => '/record',
                    'description' => 'Buka layar perekaman GPS',
                ],
                [
                    'name' => 'Feed',
                    'short_name' => 'Feed',
                    'url' => '/feed',
                ],
                [
                    'name' => 'Segment',
                    'short_name' => 'Segment',
                    'url' => '/segments',
                ],
            ],
        ];
    }
}
