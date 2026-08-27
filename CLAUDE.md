# raga-web

RAGA adalah aplikasi web tracking kesehatan & training (lari/trail running) berbasis data Garmin Connect: dashboard, skor recovery/readiness, manajemen training, analytics, plus AI Health Coach. `raga-web` adalah implementasi Laravel monolitik dari RAGA — bukan sekadar frontend untuk API terpisah, aplikasi ini SENDIRI adalah backend + web UI + penyedia data. Repo publik: `faavha-alt/raga-web`, di-deploy ke `raga.mipa.uns.ac.id` (tampaknya proyek di lingkungan kampus, Fakultas MIPA UNS) via GitHub Actions (SSH, `git reset --hard` + build di server).

## Arsitektur

- **Stack**: Laravel 13 (PHP 8.3), Blade + Alpine.js + Tailwind CSS v4 + Vite. Peta rute trail pakai Leaflet. Chart dashboard tampaknya komponen Blade buatan sendiri (`components/sample-chart.blade.php`, `category-bar-chart.blade.php`, `chart-math.blade.php`), bukan library JS charting.
- **Auth**: tiga lapis —
  - Laravel Breeze (session) untuk login web biasa.
  - Sanctum (personal access token) untuk API `/api/*` dan bridge MCP lokal.
  - Passport (OAuth2 server penuh, termasuk dynamic client registration & `.well-known` metadata) untuk endpoint MCP jarak jauh (`POST /mcp`) agar klien MCP mana pun bisa connect langsung by URL.
- **Sumber data**: Garmin Connect. Alurnya tidak langsung dari PHP — `scripts/garmin_sync.py` + `garmin_login.py` (Python, pakai lib `garminconnect`) login ke Garmin dan mencetak JSON ke stdout, lalu `php artisan garmin:import` (`app/Console/Commands/ImportGarminData.php`) membaca JSON itu dan menulis ke tabel-tabel app. Ada juga command `garmin:calculate-recovery-scores` dan `garmin:analyze-training-load` (lihat `app/Console/Commands/`).
- **Domain inti** (lihat `app/Services/`): Dashboard (today snapshot, insight rule-based), Health (vitals + personal baseline), Recovery Engine (skor recovery & readiness 0-100 dengan breakdown faktor transparan), Training (acute:chronic load, monotony, konsistensi, kalender, distribusi aktivitas), Running & Trail (performance rating, PR, elevation profile, pengelompokan rute), Analytics (korelasi antar-metrik).
- **AI Health & Performance Coach** (`app/Services/Ai/`): BYOK (Bring Your Own Key) per-user, multi-provider (`AiProviderFactory` → `AnthropicProvider` / `GeminiProvider`, API key diatur di Settings > AI Coach). Setiap request AI di-rebuild ulang context-nya lewat `AiContextBuilder` dan disuntik sebagai `RAGA_CONTEXT` di system prompt — model dilarang menebak angka di luar context ini, dan wajib memisahkan data vs inferensi (aturan eksplisit di prompt `AiCoachService`, termasuk larangan diagnosis medis).
- **Server MCP RAGA** — ini bagian paling relevan untuk lingkungan kerja saat ini: repo ini adalah implementasi backend dari tool MCP `raga` (raga_overview, raga_training, raga_recovery, raga_health, raga_running, raga_trail, raga_save_training_plan, raga_save_recommendation) yang tersedia di environment Claude ini. Ada dua jalur:
  1. **Bridge lokal stdio** — `mcp-server/index.js`, forward tiap tool ke REST API hosted (`https://raga.mipa.uns.ac.id/api` default, lihat `RAGA_API_BASE`/`RAGA_API_TOKEN`) yang diimplementasi di `app/Http/Controllers/Api/McpController.php`.
  2. **Endpoint MCP jarak jauh** — `POST /mcp` (`app/Http/Controllers/Mcp/McpTransportController.php`, middleware `mcp.auth` / `EnsureMcpAuthenticated`) dengan OAuth penuh via Passport, jadi klien mana pun bisa connect langsung tanpa jalankan bridge lokal.
- **Relasi dengan repo `raga` (di `/ai/projects/raga`)**: TIDAK dibaca isinya (sesuai instruksi), tapi ada indikasi kuat dari histori commit repo ini sendiri. Commit pertama repo ini adalah `2ddea20 Phase 1 (web pivot): Laravel foundation, DB schema, auth, navigation skeleton`, langsung diikuti `2d3b2df ci: add GitHub Actions workflow to auto-deploy to raga.mipa.uns.ac.id`. Frasa "web pivot" pada commit paling awal mengindikasikan `raga-web` adalah **penulisan ulang/pivot ke bentuk web dari project "RAGA" yang sudah ada sebelumnya** — kemungkinan besar itu adalah repo `raga`. Tidak ditemukan referensi langsung (URL, package, submodule) ke repo `raga` di dalam kode — hanya indikasi dari kata "pivot" itu sendiri. **(perlu konfirmasi user)** apakah `raga` memang versi lama/beda-stack dari project yang sama (mis. CLI/script murni sebelum jadi web app), atau proyek lain yang tidak berkaitan.

## Konvensi

- Struktur Laravel standar: Controllers tipis, logika bisnis dipindah ke `app/Services/<Domain>/...Service.php` atau `...Calculator.php` / `...Engine.php` / `...Gateway.php` per domain (Health, Recovery, Training, Running, Trail, Analytics, Dashboard, Ai, HealthData).
- Migrasi database bertanggal `2026_08_13_*` untuk skema inti (dibuat berurutan dalam satu hari/sesi), lalu `2026_08_16_*` untuk Sanctum/Passport/AI settings — menunjukkan skema inti dirancang di awal, auth API & AI ditambah belakangan.
- Import data eksternal (Garmin) sengaja dipisah: script Python murni ambil & cetak JSON, tidak tahu apa-apa soal skema DB RAGA; command Artisan yang urus mapping ke tabel. Ada komentar eksplisit soal enum `typeId` personal record Garmin yang tidak didokumentasikan resmi — label yang belum pasti sengaja dibiarkan sebagai `garmin_pr_type_N` daripada ditebak, karena datanya bisa dipakai AI.
- AI Coach: selalu pisahkan DATA vs INFERENSI di jawaban, tidak boleh mendiagnosis kondisi medis (aturan eksplisit di system prompt `AiCoachService`).
- Testing: PHPUnit (Feature + Unit) di `tests/`, penamaan `<Domain>Test.php` mengikuti nama Service/Engine yang diuji. Beberapa commit history ("Fix false-positive test failure...", "Fix Training module date-range and int/float test failures") menunjukkan pola: build fitur besar lalu commit fix terpisah untuk test yang gagal.

## Status

Lihat `PROGRESS.md` untuk progres detail dan riwayat sesi kerja.
