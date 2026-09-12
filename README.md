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
  konsistensi, dan **Relative Effort** (estimasi TRIMP dari zona HR yang dihitung
  RAGA sendiri, sehingga aktivitas non-Garmin pun punya ukuran beban).
- **Running & Trail** — performance rating, personal records, profil elevasi,
  pengelompokan rute (peta Leaflet).
- **Health** — heart & HRV, stress, body battery, metrik harian, tren jangka panjang.
- **Advanced Analytics** — korelasi antar-metrik.
- **AI Health Coach** — asisten bertenaga AI yang menjawab dengan data pribadi Anda
  sendiri, selalu memisahkan **data vs inferensi** dan tidak mendiagnosis kondisi medis.

`raga-web` adalah **aplikasi monolitik Laravel** — backend, web UI, dan penyedia
data sekaligus, bukan frontend untuk API terpisah.

## Fitur utama

### Lapisan sosial & perekaman mandiri

RAGA kini juga bisa dipakai **tanpa Garmin** dan punya sisi sosial seperti Strava:

- **Rekam GPS dari browser** (`/record`) — jarak, pace, elevasi, peta langsung,
  tombol jeda, dan mode darurat tanpa GPS (treadmill).
- **Feed sosial** (`/feed`) — aktivitas orang yang Anda ikuti, dengan kudos dan
  komentar.
- **Profil atlet publik** (`/@username`) — statistik dari aktivitas yang boleh
  Anda lihat, pengikut/mengikuti, dan tombol ikuti. Profil bisa dibuat privat.
- **Segment & leaderboard** (`/segments`) — buat segment dari aktivitas sendiri,
  RAGA mencari effort pada aktivitas lain, lalu menampilkan peringkat per atlet.
- **Privasi per aktivitas** — `Semua orang`, `Pengikut saja`, atau `Hanya saya`.
  Default aplikasi adalah **hanya saya**.
- **PWA** — bisa dipasang ke layar utama HP dan punya halaman offline.
- **Notifikasi** — pemberitahuan follow, kudos, dan komentar.
- **Masuk/daftar dengan Google** — pendaftaran tanpa formulir; username dan foto
  profil dibuat otomatis. Tombolnya tersembunyi sampai kredensial Google diisi.

> **Data kesehatan tidak pernah masuk permukaan sosial.** Lihat bagian
> [Privasi data kesehatan](#privasi-data-kesehatan) di bawah.

### Sumber data: Garmin Connect
Data diambil dari Garmin Connect melalui script Python (`scripts/garmin_sync.py`)
yang login ke Garmin lalu mencetak JSON, kemudian diimpor ke database oleh
`php artisan garmin:import`. Cakupan sinkronisasi meliputi aktivitas, body battery,
VO2max, respirasi, training readiness, personal records, time-series per-workout,
GPS route, dan lap.

### Sumber data: Suunto (read-only)

Selain Garmin, RAGA bisa menarik data dari **Suunto App** lewat Suunto Cloud API
(OAuth2 + subscription key, tanpa skrip Python — token disimpan terenkripsi di
tabel `suunto_connections`). Workout dipetakan ke tabel aktivitas yang sama
(`source = suunto`), jadi Recovery, Training, Analytics, dan AI Coach otomatis
ikut. Yang tidak tersedia dari Suunto: Body Battery, Training Readiness, dan
respirasi — faktor itu dibiarkan kosong, bukan ditebak.

Aktivasi (butuh **Suunto Partner Program** — lihat catatan di bawah):

1. Ajukan **Suunto Partner Program** lewat
   <https://www.suunto.com/en-gg/partners/welcome-partners/> (formulir:
   <https://survey.alchemer.eu/s3/90553908/PARTNER-Become-a-Suunto-Partner>).
   Centang **Suunto Cloud API** → tanda tangani API agreement → sebutkan email
   developer yang butuh akses. Jawaban biasanya ≤ 2 minggu; kabar lewat
   `partners@suunto.com`.
2. Setelah diterima: login `https://apizone.suunto.com`, langganan **Developer
   API** → salin *subscription key*, lalu isi OAuth settings (app name, client
   secret, redirect URI `https://<host>/settings/suunto/callback`).
3. Set di `.env`: `SUUNTO_CLIENT_ID`, `SUUNTO_CLIENT_SECRET`,
   `SUUNTO_SUBSCRIPTION_KEY`, `SUUNTO_REDIRECT_URI` → `php artisan config:cache`.
4. Buka **Settings → Suunto → Hubungkan**, lalu pakai tombol *Sync Now*.
   Cron opsional: `php artisan suunto:sync --days=7`.

Batasan yang perlu diketahui (dari FAQ resmi Suunto):
- Akses **tidak diberikan untuk pemakaian pribadi** — hanya organisasi/perusahaan
  (komersial maupun non-komersial). Untuk pemakaian pribadi, alternatifnya ekspor
  FIT/GPX dari Suunto App atau lewat Strava.
- Yang tersedia: **workout** (GPS track, sampel HR, lap — tergantung model jam) dan
  **daily activity** (langkah, kalori). **Data tidur, berat badan, dan HR zone
  belum tersedia** di Cloud API — jadi skor recovery dari Suunto akan kehilangan
  faktor tidur/HRV.
- Detail per-detik paling lengkap ada di **FIT file**; JSON workout hanya ringkasan.
- Ada kuota panggilan (Developer API di-rate-limit), jadi ambil daftar sekali per
  rentang tanggal, bukan per aktivitas.
- **SuuntoPlus Editor bukan jalur data.** Editor (ekstensi VS Code,
  <https://apizone.suunto.com/suuntoplusEditor>) memang bisa dipakai **tanpa**
  Partner Program, tapi gunanya membuat aplikasi yang berjalan **di jam** (manifest
  + JS + template HTML) saat aktivitas berlangsung — bukan menarik riwayat workout.
  Untuk RAGA (menarik data ke server), tetap harus lewat Partner Program + Cloud API.
  Menerbitkan SuuntoPlus app pun tetap butuh Partner Program.
- **Jalur alternatif "seperti Garmin" (tidak resmi).** RAGA sendiri menarik data
  Garmin lewat klien tidak resmi (`garminconnect`, bukan Garmin Health API). Analog
  untuk Suunto adalah CLI **`suuntool`** (<https://github.com/tajchert/suuntool>,
  Go) yang memakai backend aplikasi Suunto: `workouts list/get/sml/fit` plus wellness
  `sleep`/`activity`/`recovery`/`sleepstages` — jadi **lebih lengkap** dari Cloud API
  resmi (yang tidak punya data tidur). Risiko: API privat, bisa berubah sewaktu-waktu,
  **berpotensi melanggar ToS Suunto**, kuota ketat, dan akun bisa di-flag/ban; hanya
  untuk data sendiri.

#### Memasang jalur suuntool (tidak resmi, read-only)

```bash
# Di server produksi (Linux amd64) — sesuaikan versinya bila sudah lebih baru.
curl -fsSL -o /tmp/suuntool.tar.gz \
  https://github.com/tajchert/suuntool/releases/download/v0.10.0/suuntool_0.10.0_linux_amd64.tar.gz
tar -xzf /tmp/suuntool.tar.gz -C /tmp suuntool
sudo install -m 0755 /tmp/suuntool /usr/local/bin/suuntool

# Pastikan dikenali aplikasi (cron PHP mungkin tidak punya /usr/local/bin di PATH):
php artisan tinker --execute="var_dump(\App\Services\Suunto\SuuntoToolClient::isAvailable());"
```

Lalu di **Settings → Suunto**, isi email & password Suunto App (password tidak
disimpan — hanya dipakai sekali untuk membuat sesi di
`storage/app/suunto_sessions/<user>/session.json`), dan tekan **Sync Now**.
Cron opsional: `php artisan suunto:sync --days=7`.

Kalau binary tidak ada di PATH cron, set `SUUNTO_TOOL_BINARY=/usr/local/bin/suuntool`
di `.env` lalu `php artisan config:cache`.

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

1. **Endpoint remote** — `POST /mcp` (JSON-RPC 2.0, transport Streamable HTTP).
   Autentikasi: OAuth penuh (Passport, dynamic client registration) **atau**
   token statis — `Authorization: Bearer <token>` dengan personal access token
   (Sanctum) yang dibuat di **Settings > API Tokens**, untuk klien yang tak bisa
   menjalankan alur login browser.
2. **Bridge lokal stdio** — `mcp-server/index.js` yang meneruskan setiap tool ke
   REST API hosted (`/api/*`), pakai token yang sama di `RAGA_API_TOKEN`.

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

### Masuk dan daftar dengan Google

Tombol Google muncul otomatis begitu kredensialnya diisi, dan **tersembunyi
selama kosong** supaya tidak ada tombol yang menabrak halaman error Google di
lingkungan yang belum dikonfigurasi.

1. Buka [Google Cloud Console](https://console.cloud.google.com/) → **APIs &
   Services** → **OAuth consent screen** (sekarang bernama *Google Auth
   Platform*). Pilih **External**, isi nama aplikasi, email dukungan, dan email
   developer.
2. Untuk dibuka ke publik, klik **Publish app**. Ini **tidak butuh review
   Google**: scope yang dipakai RAGA (`openid`, `email`, `profile`) termasuk
   non-sensitif, jadi verifikasi hanya diperlukan kalau kelak Anda meminta akses
   ke data Google lain.
3. **Credentials** → **Create credentials** → **OAuth client ID** → tipe
   **Web application**. Di **Authorized redirect URIs** tambahkan tepat:

   ```
   https://raga.favha.cloud/auth/google/callback
   http://localhost:8000/auth/google/callback   # opsional, untuk dev lokal
   ```

   Alamat harus sama persis, termasuk skema dan tanpa garis miring di akhir;
   kalau tidak, Google membalas `redirect_uri_mismatch`.
4. Isi `.env`:

   ```bash
   GOOGLE_CLIENT_ID=xxxxxxxx.apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=xxxxxxxx
   GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
   ```

   Lalu segarkan cache konfigurasi: `php artisan config:cache`.

Perilaku akun yang dijaga oleh test (lihat `tests/Feature/GoogleAuthTest.php`):

- Akun baru mendapat **username otomatis** dari nama (diunifikasi agar unik), dan
  **tidak punya password** (`users.password` NULL).
- Masuk dengan Google memakai email yang **sudah terdaftar** akan **menautkan**
  ke akun lama — tetapi hanya bila Google menyatakan emailnya terverifikasi.
  Kalau tidak, permintaan ditolak dan pengguna diarahkan masuk dengan password.
- Nama tampilan yang sudah diubah pengguna di RAGA **tidak** ditimpa oleh nama
  dari Google, dan email lokal tidak diubah.
- Foto profil Google **diunduh ke `public/uploads/avatars`**, tidak di-hotlink,
  supaya browser pengunjung tidak memanggil server Google saat membuka profil.
  Kegagalan unduhan tidak menggagalkan pendaftaran — pengguna tampil dengan
  inisial.
- Pengguna Google bisa **membuat password sendiri** dan **menghapus akunnya**
  tanpa mengisi password lama (kolomnya memang NULL) — lihat `User::hasPassword()`.

### Sinkronisasi data Garmin (manual)

```bash
# Login ke Garmin lalu cetak JSON ke stdout
python3 scripts/garmin_login.py

# Impor JSON ke database app
php artisan garmin:import
```

### Backfill riwayat panjang (1–3 tahun)

Tombol *Sync Now* di Settings sengaja hanya menarik beberapa hari terakhir.
Untuk menarik riwayat panjang, jalankan dari terminal server — prosesnya
lama, jadi jangan lewat browser:

```bash
# Garmin: 2 tahun, dipotong 60 hari per proses (terbaru dulu, aman diulang)
php artisan garmin:sync --days=730 --chunk=60

# Suunto: 2 tahun, daftar ditarik sekali dengan --stream (auto-paginasi)
php artisan suunto:sync --days=730 --samples=auto
```

- `garmin:sync` memakai `--offset` di `scripts/garmin_sync.py` supaya tiap
  chunk mengambil jendela tanggal yang berbeda; `recovery:calculate` ikut
  dijalankan per chunk. Chunk yang gagal menghentikan proses tanpa menghapus
  hasil chunk sebelumnya.
- `suunto:sync --samples=auto` hanya mengunduh sampel per-detik (~5 MB per
  workout) untuk 30 hari terakhir; `--samples=all` mengunduh semuanya (bisa
  berjam-jam), `--samples=none` hanya ringkasan workout.
- Keduanya idempoten: workout yang sudah ada dilewati, jadi aman dijalankan
  ulang untuk melanjutkan.

## Testing

```bash
php artisan test                     # SQLite in-memory (cepat, default)
vendor/bin/phpunit -c phpunit.mysql.xml   # suite yang sama, terhadap MySQL
```

Test suite mencakup unit test untuk engine/calculator (recovery, training load,
running performance, dll.) dan feature test untuk halaman serta alur auth.

Default suite berjalan di **SQLite in-memory**. SQLite menerima banyak hal yang
ditolak MySQL (alias reserved word seperti `SUM(x) as load`, pelanggaran
`ONLY_FULL_GROUP_BY`, perbedaan tipe kolom), jadi ada konfigurasi kedua
`phpunit.mysql.xml` untuk menjalankan suite terhadap MySQL — itulah yang dipakai
job `test-mysql` di CI.

## CI/CD

- **`ci.yml`** — dua job, keduanya harus hijau:
  - `test`: PHP 8.4 + Node 24, `composer install` → `npm run build` →
    `php artisan test` (SQLite), jalan di setiap push ke `main` dan PR.
  - `Tests (MySQL)`: service MySQL 8, `php artisan migrate --force --database=mysql`
    → `vendor/bin/phpunit -c phpunit.mysql.xml`. Menangkap bug yang hanya muncul
    di MySQL sebelum sampai ke produksi.
- **`deploy.yml`** — deploy otomatis ke `raga.favha.cloud` via SSH
  (`git reset --hard` + build di server). Di-gate sebagai `workflow_run` yang hanya
  jalan kalau **seluruh CI** hijau; `workflow_dispatch` tetap bisa deploy manual.

## Deploy ke CloudPanel (server mandiri)

Skrip `deploy.yml` sudah menjalankan `composer install`, `npm run build`,
`php artisan migrate --force` dan cache. Beberapa hal khas server mandiri
**tidak** tercakup otomatis dan perlu disiapkan sekali:

```bash
# 1. Foto profil: direktori unggahan harus bisa ditulis proses PHP-FPM.
#    Aplikasi menulis ke public/uploads/avatars (tanpa symlink storage:link,
#    karena deploy tidak menjalankannya).
mkdir -p public/uploads/avatars
chmod -R 775 public/uploads

# 2. Isi username untuk pengguna lama (registrasi baru sudah otomatis).
#    Kolom `username` sengaja nullable agar migrasi aman pada tabel terisi.
php artisan users:backfill-usernames --dry-run   # tinjau dulu
php artisan users:backfill-usernames
```

**Kredensial Google tidak ikut ter-deploy.** `deploy.yml` tidak menyentuh `.env`,
jadi isi `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, dan `GOOGLE_REDIRECT_URI` di
`.env` server (lihat bagian *Masuk dan daftar dengan Google*). Karena setiap
deploy menjalankan `php artisan config:cache`, nilai baru terbaca pada deploy
berikutnya; kalau tidak ingin menunggu, jalankan langsung di server:

```bash
cd ~/htdocs/raga.favha.cloud
php artisan config:cache
```

**Jangan menyimpan cadangan `.env` di dalam direktori repo.** `.gitignore`
mengabaikan seluruh pola `.env*` kecuali `.env.example`, tetapi berkas cadangan
yang berada di root repo tetap satu `git add -A` dari ikut ter-commit beserta
seluruh rahasia produksi. Simpan di luar repo, misalnya:

```bash
mkdir -p ~/.env-backups
cp -a .env ~/.env-backups/.env.$(date +%Y%m%d%H%M%S)
chmod 600 ~/.env-backups/.env.*
```

**nginx (template vhost CloudPanel)** — tambahkan blok agar service worker PWA
tidak di-cache selamanya oleh Varnish/proxy (kode `sw.js` berubah saat aplikasi
diperbarui; bila di-cache, pembaruan versi cache tidak akan pernah sampai):

```nginx
location = /sw.js {
    add_header Cache-Control "no-cache, no-store, must-revalidate";
    try_files $uri =404;
}
```

**Manifest PWA tidak perlu konfigurasi nginx.** `/manifest.webmanifest` disajikan
lewat rute aplikasi (`App\Support\WebManifest`), bukan sebagai berkas statis,
karena nginx mengirim ekstensi `.webmanifest` sebagai `application/octet-stream`
dan browser menolak manifest dengan tipe MIME itu — akibatnya aplikasi tidak bisa
dipasang ke layar utama. Jangan membuat `public/manifest.webmanifest`: berkas
statis akan menutupi rute tersebut. Ada test yang menjaga hal ini.

Catatan operasional:

- **Tidak butuh queue worker.** Notifikasi sosial (follow, kudos, komentar)
  dikirim sinkron lewat kanal `database`, jadi `QUEUE_CONNECTION` boleh tetap
  `database` tanpa menjalankan `queue:work`.
- **Perekaman GPS berjalan di browser**, jadi tidak ada proses latar di server.
  Sesi panjang hanya dibatasi oleh perangkat pengguna.
- Setelah memperbarui kode, jalankan `php artisan queue:restart` hanya bila Anda
  kelak mengaktifkan worker.

## Privasi data kesehatan

RAGA menyimpan data kesehatan yang sensitif (HRV, tidur, stress, body battery,
skor recovery/readiness). Batas yang **dijaga oleh kode dan test**:

- Permukaan sosial (feed, profil atlet, leaderboard, notifikasi) hanya pernah
  menampilkan data aktivitas dari tabel `workouts`.
- Visibilitas aktivitas default **`private`**. Aktivitas lama hasil impor Garmin
  tidak pernah otomatis menjadi publik.
- Semua daftar aktivitas melewati query scope `Workout::visibleTo()`, sehingga
  aturan privasi diterapkan terpusat. Aktivitas yang tidak boleh dilihat
  menghasilkan **404**, bukan 403, agar keberadaannya tidak terkonfirmasi.
- `users.is_public = false` membuat profil & statistik hanya terlihat oleh
  pengikut.

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
