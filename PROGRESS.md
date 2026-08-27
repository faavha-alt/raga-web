# Progress — raga-web

Dibuat: 2026-08-26

## Tasks

<!-- Format checklist standar: "- [ ] belum" / "- [x] selesai". Dibaca otomatis oleh Project Dashboard (http://100.94.175.72:4400/) untuk menghitung progres. -->

### Fitur inti (selesai, berdasarkan histori commit & kode)
- [x] Fondasi Laravel: DB schema, auth (Breeze), navigation skeleton ("Phase 1 web pivot")
- [x] CI deploy otomatis ke raga.mipa.uns.ac.id via GitHub Actions (SSH)
- [x] Pipeline ingestion data Garmin Connect (script Python `garmin_sync.py`/`garmin_login.py` + `php artisan garmin:import`)
- [x] UI Settings untuk connect/sync Garmin (connect, sync manual, disconnect)
- [x] Perluasan data sync Garmin (body battery, VO2max, respirasi, training readiness, personal records, time-series per-workout, GPS route, laps)
- [x] Dashboard utama: Today snapshot, Recent Activity, Weekly Training, Health Trend, Insights berbasis rule
- [x] Modul Activity Management: list ter-cari/filter/sort + halaman detail dengan chart HR/pace/elevation
- [x] Modul Health Management: Overview, Heart & HRV, Stress, Body Battery, Daily Metrics, tren 7D/30D/90D/1Y, personal baseline
- [x] Recovery & Readiness Engine: skor 0-100 transparan dengan breakdown faktor (sleep, HRV, resting HR, stress, training load, body battery, aktivitas)
- [x] Modul Training Management: kalender, load, volume, distribusi aktivitas, konsistensi
- [x] Modul Running & Trail Analytics: performance rating, personal records, elevation profile, pengelompokan rute
- [x] Modul Advanced Analytics: korelasi antar-metrik
- [x] API ber-token (Sanctum) untuk akses data MCP (`app/Http/Controllers/Api/McpController.php`)
- [x] Local MCP server (stdio, `mcp-server/index.js`) sebagai bridge ke API hosted
- [x] Remote MCP endpoint (`POST /mcp`) dengan OAuth penuh (Passport, dynamic client registration) — bisa dipakai klien MCP mana pun langsung by URL
- [x] Write-back tools: AI bisa menyimpan training plan & recommendation balik ke RAGA (`raga_save_training_plan`, `raga_save_recommendation`)
- [x] AI Health & Performance Coach: context builder (`RAGA_CONTEXT`), BYOK multi-provider (Anthropic & Gemini), UI setup API key di Settings, fix bug crash (sprintf `%` collision), fix default model Gemini (2.5-flash, bukan versi terbaru yang mahal/belum stabil)
- [x] Rework app shell dari top nav ke sidebar layout, full-width content

### Belum selesai / perlu tindak lanjut
- [x] Setup environment lokal: `composer install` + `npm install` + `.env` dari `.env.example` + `APP_KEY` + `database/database.sqlite` + `php artisan migrate` + `php artisan passport:keys` + `npm run build`. `php artisan test` hijau: 138 passed / 374 assertions. (2026-08-27)
- [x] README.md ditulis ulang jadi dokumentasi RAGA (bukan lagi boilerplate Laravel default). (2026-08-27)
- [x] CI test workflow: `.github/workflows/ci.yml` (PHP 8.4 + Node 24, `composer install` → `npm run build` → `php artisan test`) jalan di tiap push ke `main` + PR. `deploy.yml` diubah jadi `workflow_run` yang hanya deploy kalau CI hijau (manual `workflow_dispatch` tetap bisa langsung deploy). (2026-08-27)
- [ ] Klarifikasi relasi dengan repo `raga` (`/ai/projects/raga`): commit pertama repo ini berlabel "Phase 1 (web pivot)", mengindikasikan `raga-web` adalah hasil pivot dari versi non-web project RAGA sebelumnya — kemungkinan besar repo `raga` itu sendiri, tapi tidak ada bukti langsung (URL/referensi kode) di dalam repo ini (perlu konfirmasi user)

## Log sesi

### 2026-08-26
- Ditambahkan ke Project Workspace, dokumentasi diisi berdasarkan eksplorasi kode existing (bukan dari rencana asli developer).

### 2026-08-27
- Verifikasi akses SSH ke server produksi: dibuat key `~/.ssh/id_ed25519_raga_web` + alias `raga-web` (203.6.149.150:1103, user `ragamipa`). Login OK, kode server = lokal (commit `6158297`, `main`), tidak ada file tracked yang dimodifikasi di server. Deploy terakhir server: 2026-08-16.
- Setup environment lokal selesai (lihat checklist di atas). App boot OK, 86 route, test suite 138/138 hijau.
- Dibuat `ci.yml` (test workflow) + `deploy.yml` di-gate ke hasil CI. Deploy push-triggered sekarang hanya jalan kalau CI sukses; `workflow_dispatch` tetap bisa deploy manual tanpa nunggu CI. **Commit `610d51f` belum bisa di-push** — token GitHub di mesin ini tak punya scope `workflow` (perlu `gh auth refresh -h github.com -s workflow`).
- AI Coach readability: balasan AI sekarang dirender sebagai Markdown → HTML (renderer kecil, HTML-escaped, subset: paragraf/list/bold/italic/code/heading) dengan jarak antar-blok yang benar (`.ai-message` styles di `ai/index.blade.php`). Bubble asisten dilebarkan, `space-y-5`. System prompt `AiCoachService` ditambah rule formatting (paragraf dipisah baris kosong, bullet `- `, angka penting **bold**, ~180 kata). `AnthropicProvider` MAX_TOKENS 2048 → 4096. Hint model Gemini pro ditambah di Settings.
- Commit `ddb8f10` (AI Coach readability) di-push ke `origin/main` dan **di-deploy manual via SSH** (`git merge --ff-only origin/main` + `view:cache`/`config:cache`/`route:cache`). Live: `raga.mipa.uns.ac.id/` → 200, `/ai` → 302 login, log bersih (error terakhir masih dari 2026-08-16). Commit CI `03b17b4` tetap lokal (butuh scope `workflow`).
- Review kualitas + pengembangan (akses tulis dibuka, danger-full-access): (1) `README.md` ditulis ulang jadi dokumentasi RAGA; (2) `McpTransportController::toolsCall` diperbaiki — error `Throwable` tak lagi membocorkan `$e->getMessage()` ke klien (log detail + pesan generik); (3) test baru `tests/Feature/McpApiWriteBackTest.php` (6 test: auth, saveTrainingPlan nested + scoping + validasi, saveRecommendation). Full suite **144 passed / 400 assertions** (naik dari 138/374).
