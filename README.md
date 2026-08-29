<div align="center">

# RAGA — Health & Training Tracker

Aplikasi web untuk melacak kesehatan dan training **lari / trail running**,
ditenagai data dari **Garmin Connect** — lengkap dengan skor recovery/readiness,
manajemen training, analytics, dan **AI Health Coach**.

![Laravel](https://img.shields.io/badge/Laravel-13-red?logo=laravel)
![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php)
![Tailwind](https://img.shields.io/badge/Tailwind-v4-38BDF8?logo=tailwindcss)
![Tests](https://github.com/faavha-alt/raga-web/actions/workflows/ci.yml/badge.svg)

</div>

## Apa itu RAGA?

RAGA adalah aplikasi **tracking kesehatan & training** yang mengambil data dari
akun Garmin Connect Anda dan mengolahnya menjadi wawasan yang bisa ditindaklanjuti:

- **Dashboard** — snapshot kondisi hari ini + insight otomatis.
- **Recovery & Readiness** — skor 0–100 yang transparan, dengan breakdown faktor
  (sleep, HRV, resting HR, stress, training load, body battery).
- **Training** — kalender, beban latihan (acute:chronic load), volume, distribusi,
  konsistensi.
- **Running & Trail** — performance rating, personal records, profil elevasi,
  pengelompokan rute (peta Leaflet).
- **Health** — heart & HRV, stress, body battery, metrik harian, tren jangka panjang.
- **Advanced Analytics** — korelasi antar-metrik.
- **AI Health Coach** — asisten bertenaga AI yang menjawab dengan data pribadi Anda
  sendiri, selalu memisahkan **data vs inferensi** dan tidak mendiagnosis kondisi medis.

`raga-web` adalah **aplikasi monolitik Laravel** — backend, web UI, dan penyedia
data sekaligus, bukan frontend untuk API terpisah.

## Fitur utama

### Sumber data: Garmin Connect
Data diambil dari Garmin Connect melalui script Python (`scripts/garmin_sync.py`)
yang login ke Garmin lalu mencetak JSON, kemudian diimpor ke database oleh
`php artisan garmin:import`. Cakupan sinkronisasi meliputi aktivitas, body battery,
VO2max, respirasi, training readiness, personal records, time-series per-workout,
GPS route, dan lap.

### AI Health & Performance Coach
- **BYOK** (Bring Your Own Key) per user — pilih provider **Anthropic** atau **Gemini**
  dan masukkan API key sendiri di *Settings > AI Coach*.
- Setiap percakapan merekonstruksi konteks data pengguna segar (`RAGA_CONTEXT`)
  dan menyuntikkannya ke system prompt. Model hanya melihat ringkasan terstruktur
  yang sama dengan yang dirender halaman app — bukan dump database mentah.
- Aturan eksplisit di prompt: pisahkan **data vs inferensi**, jangan pernah
  mendiagnosis kondisi medis, gunakan bahasa berkalibrasi, jelaskan alasan di balik
  rekomendasi.

### Server MCP (Model Context Protocol)
Repo ini adalah backend dari tool MCP `raga`. Ada dua jalur akses:

1. **Endpoint remote** — `POST /mcp` (JSON-RPC 2.0, transport Streamable HTTP)
   dengan OAuth penuh (Passport, dynamic client registration). Klien MCP mana pun
   bisa connect langsung by URL.
2. **Bridge lokal stdio** — `mcp-server/index.js` yang meneruskan setiap tool ke
   REST API hosted (`/api/*`).

Tool yang tersedia: `raga_overview`, `raga_training`, `raga_recovery`,
`raga_health`, `raga_running`, `raga_trail`, `raga_full_context`,
`raga_save_training_plan`, `raga_save_recommendation`, `raga_sync_garmin`
(tarik data terbaru dari Garmin lalu hitung ulang recovery — sinkron, ~20–60 dtk).

## Arsitektur

| Lapisan | Teknologi |
|---------|-----------|
| Framework | Laravel 13 (PHP 8.3) |
| Frontend | Blade + Alpine.js + Tailwind CSS v4 + Vite |
| Peta | Leaflet |
| Chart | Komponen Blade buatan sendiri (`components/*-chart.blade.php`) |
| Auth web | Laravel Breeze (session) |
| Auth API | Sanctum (personal access token) |
| Auth MCP remote | Passport (OAuth2 + dynamic client registration) |

Struktur domain (`app/Services/<Domain>/`): `Activity`, `Ai`, `Analytics`,
`Dashboard`, `Health`, `HealthData`, `Recovery`, `Running`, `Trail`, `Training`.

## Prasyarat

- PHP 8.3+ dengan ekstensi umum Laravel
- Composer
- Node.js 20+ & npm
- SQLite (untuk dev lokal)

## Setup lokal

```bash
# 1. Install dependency
composer install
npm install

# 2. Siapkan environment
cp .env.example .env
php artisan key:generate

# 3. Database SQLite
touch database/database.sqlite
php artisan migrate

# 4. Kunci Passport (dibutuhkan untuk endpoint MCP remote)
php artisan passport:keys

# 5. Build aset frontend
npm run build        # produksi
npm run dev          # development (hot reload)

# 6. Jalankan
php artisan serve
```

### Sinkronisasi data Garmin (manual)

```bash
# Login ke Garmin lalu cetak JSON ke stdout
python3 scripts/garmin_login.py

# Impor JSON ke database app
php artisan garmin:import
```

## Testing

```bash
php artisan test
```

Test suite mencakup unit test untuk engine/calculator (recovery, training load,
running performance, dll.) dan feature test untuk halaman serta alur auth.

## CI/CD

- **`ci.yml`** — test workflow: PHP 8.4 + Node 24, `composer install` →
  `npm run build` → `php artisan test`, jalan di setiap push ke `main` dan PR.
- **`deploy.yml`** — deploy otomatis ke `raga.favha.cloud` via SSH
  (`git reset --hard` + build di server). Di-gate sebagai `workflow_run` yang hanya
  jalan kalau CI hijau; `workflow_dispatch` tetap bisa deploy manual.

## Lisensi

Sumber kode repositori ini disediakan sebagai aplikasi sumber-terbuka di bawah
lisensi MIT (lihat bagian license framework Laravel di bawah).

---

<details>
<summary>Laravel boilerplate</summary>

Laravel is a web application framework with expressive, elegant syntax. We
believe development must be an enjoyable and creative experience.

- [Documentation](https://laravel.com/docs)
- [Laracasts](https://laracasts.com)

Laravel is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
</details>
