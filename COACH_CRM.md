# RAGA — Coach CRM (Desain & Rencana Implementasi)

> Dokumen perencanaan untuk memperluas RAGA dari *personal health/training
> tracker* menjadi **CRM untuk coach/pelatih** yang mengelola banyak atlet/klien.
> Tulisannya siap dieksekusi bertahap (mis. oleh Claude Code). Ikuti fase berurutan.

## 1. Tujuan & Visi

Coach (pelatih) bisa:
- Mengelola **banyak atlet/klien** dari satu akun.
- Melihat ringkasan & detail **training/recovery/health tiap atlet** (read-only, berbasis konsen).
- Menetapkan **training plan** & **rekomendasi** ke atlet tertentu.
- Melacak **kepatuhan** (apakah atlet menyelesaikan workout terencana).
- Menggunakan **AI Coach per-atlet** untuk membantu analisa.
- (Opsional) Menulis catatan/komunikasi per atlet.

Prinsip kunci: **data tetap milik atlet** (per-user, sudah seperti sekarang). Coach hanya
mendapat akses baca via relasi yang dibuat dengan konsen.

## 2. Kondisi Saat Ini (fondasi yang dipakai ulang)

- Semua tabel data sudah punya `user_id` → mudah diakses lintas user.
- Modul siap: Dashboard, Training (+ plan & goals & progress), Recovery, Health,
  Running/Trail, Analytics, **AI Coach**, **MCP** (`raga_*` tools).
- Auth: Breeze (session) + Sanctum (API) + Passport (OAuth/MCP remote).
- `User` belum punya konsep role. Tidak ada relasi coach–atlet.
- `TrainingPlan` sudah punya `training_goal_id` (opsional) dan relasi `goal()`.

## 3. Keputusan (asumsi default — ubah bila perlu)

1. **Role**: tambah kolom `role` di `users` (`athlete` default, `coach`). Satu user bisa
   dijadikan coach. (Paling sederhana; role banyak/team bila perlu di fase lanjut.)
2. **Coach juga user biasa**: coach TETAP punya data latihan sendiri (role tidak
   menghapus akses ke datanya). Praktis dan fleksibel.
3. **Cara atlet masuk (rekomendasi)**: atlet mendaftar sendiri sebagai user RAGA
   (sambungkan Garmin sendiri), lalu **menghubungkan ke coach**. Coach melihat atlet
   yang sudah terhubung. (Data Garmin tiap atlet tetap milik atlet itu.)
4. Relasi dibuat via **undangan/link** sederhana (code) di fase 1; tingkatan (accept/
   reject) opsional di fase lanjut.

## 4. Perubahan Data Model (migrasi)

### 4.1 `users` — tambah role
```php
Schema::table('users', function (Blueprint $table) {
    $table->string('role')->default('athlete')->index(); // 'athlete' | 'coach'
});
```
Model: tambah `'role'` ke `#[Fillable]`. Helper `isCoach()` / `isAthlete()`.

### 4.2 Tabel `coach_athlete` (pivot relasi coach–atlet)
```php
Schema::create('coach_athlete', function (Blueprint $table) {
    $table->id();
    $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('athlete_id')->constrained('users')->cascadeOnDelete();
    $table->string('status')->default('active');   // active | pending
    $table->string('relation_code')->nullable();   // kode undangan
    $table->timestamps();
    $table->unique(['coach_id', 'athlete_id']);
});
```

Relasi di `User`:
```php
// coach → atlet yang ia kelola
public function coachedAthletes(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'coach_athlete', 'coach_id', 'athlete_id')
        ->withPivot('status', 'relation_code')->withTimestamps();
}
// atlet → coach yang mengelolanya
public function coaches(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'coach_athlete', 'athlete_id', 'coach_id')
        ->withPivot('status', 'relation_code')->withTimestamps();
}
```

### 4.3 (Fase lanjut, opsional) catatan coach per atlet
```php
Schema::create('coach_notes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('athlete_id')->constrained('users')->cascadeOnDelete();
    $table->text('body');
    $table->timestamps();
});
```

## 5. Akses & Kebijakan (Policies)

Buat `App\Policies\CoachAthletePolicy` (atau cek inline di controller) dengan aturan:
- Coach hanya bisa melihat data atlet yang ADA di relasi `coach_athlete` (status active).
- Coach TIDAK bisa membaca data atlet lain di luar relasi.
- Atlet hanya melihat data & plan miliknya sendiri (tidak berubah).

Helper: `CoachAthletePolicy::canView(User $coach, User $athlete): bool`
```php
return $coach->id !== $athlete->id
    && $coach->isCoach()
    && $coach->coachedAthletes()->where('athlete_id', $athlete->id)->where('status','active')->exists();
```

Semua route coach wajib memanggil policy ini. Abaikan yang bukan coach → 403/redirect.

## 6. Rute & Controller

Tambahkan grup route `middleware(['auth','verified'])` (prefix `/coach`):

```
GET  /coach/dashboard                      CoachController@index     (daftar atlet + ringkasan)
GET  /coach/invite                         CoachController@invite    (form buat kode undangan)
POST /coach/invite                         CoachController@storeInvite (generate kode)
POST /coach/invite/use                     CoachController@useInvite (atlet masukkan kode utk terhubung)
GET  /coach/athletes/{athlete}             CoachController@athlete   (detail atlet utk coach)
GET  /coach/athletes/{athlete}/training    CoachController@athleteTraining
GET  /coach/athletes/{athlete}/recovery    CoachController@athleteRecovery
POST /coach/athletes/{athlete}/plan        CoachController@assignPlan      (assign training plan)
POST /coach/athletes/{athlete}/recommendation CoachController@assignRecommendation
DELETE /coach/athletes/{athlete}           CoachController@unlink          (lepas atlet)
```

`CoachController` memakai ulang service yang sama dengan halaman user (TrainingStatusEngine,
RecoveryEngine, HealthTrendService, dsb.) tapi dengan user target = atlet.

## 7. View / UI

- `resources/views/coach/dashboard.blade.php` — daftar kartu atlet: nama, ringkasan
  recovery/readiness, training load minggu ini, progress goal/plan, status.
- `resources/views/coach/athlete.blade.php` — header atlet + tab (Training/Recovery/Health).
- `resources/views/coach/invite.blade.php` — kode undangan + cara pakai.
- Tambah item nav `Coach` (hanya tampil jika `isCoach()`), mis. di `layouts/navigation.blade.php`.

Reuse komponen chart (`x-health-trend-chart`, `x-weekly-bar-chart`, `x-card`) untuk
konsistensi visual.

## 8. AI Coach untuk coach (Fase lanjut)

- `AiCoachService::reply()` sudah menerima `User $user`. Coach bisa menganalisa atlet
  dengan memanggil service dengan `$athlete` sebagai user.
- Tambah pilihan di halaman atlet: "Tanya AI tentang atlet ini".
- Konteks AI (`AiContextBuilder::buildFor($athlete)`) otomatis memakai data atlet.

## 9. Implikasi MCP

- Tool `raga_*` saat ini berjalan sebagai user yang terautentikasi (token). Untuk coach
  melihat data atlet via MCP, tambahkan tool/parameter `athlete_id` yang divalidasi
  lewat policy. (Fase lanjut; tidak memblokir fase 1–3.)

## 10. Rencana Implementasi Bertahap

### Fase 0 — Validasi (sebelum kode besar)
1. Wawancara **10–15 orang yang benar-benar melatih pelari berbayar di Indonesia**
   (cari via Ruang Lari, coach TrainingPeaks Indonesia, komunitas trail). Tanya:
   berapa atlet berbayar, pakai alat apa sekarang, apa yang paling menyita waktu,
   mau bayar berapa/bln untuk alat yang menghematnya.
2. Cek jalur **Garmin API resmi** (Health API / Developer Program): syarat approval,
   ToS untuk layanan komersial multi-user, biaya. Ini penentu apakah CRM berbayar
   layak (lihat §Risiko di Lampiran).
3. Kriteria lanjut: mayoritas narasumber punya ≥5 atlet berbayar DAN menyatakan mau
   bayar. Jika mayoritas <5 atlet & pakai alat gratis → arahkan ke marketplace/B2C
   atau fokus pasar coach berbahasa Inggris (trail global), bukan SaaS coach lokal.

### Fase 1 — Fondasi (role + relasi + dashboard coach)
1. Migrasi: `role` di `users` + tabel `coach_athlete`.
2. Model `User`: fillable role, helper `isCoach()`, relasi `coachedAthletes()`/`coaches()`.
3. Policy `CoachAthletePolicy`.
4. `CoachController@index` (daftar atlet + ringkasan) + view `coach/dashboard`.
5. Nav `Coach` (conditional).
6. Test: role, relasi, dashboard hanya untuk coach, atlet tak boleh akses route coach.

### Fase 2 — Detail atlet + assign plan/rekomendasi
1. `CoachController@athlete*` (training/recovery/health) memakai service existing dgn atlet.
2. Assign plan (reuse logika `McpController::saveTrainingPlan` tapi untuk `athlete_id`,
   validasi policy).
3. Assign recommendation (reuse `saveRecommendation`).
4. View detail atlet + tab.
5. Test: coach hanya bisa assign ke atlet yang terhubung.

### Fase 3 — Kepatuhan + komunikasi
1. Ringkasan kepatuhan plan (X/Y workout selesai, reuse `completedPlannedWorkouts()`).
2. `coach_notes` + UI catatan per atlet.
3. Test kepatuhan.

### Fase 4 — AI per-atlet + (opsional) MCP multi-atlet
1. AI coach per atlet dari halaman coach.
2. MCP tool dengan `athlete_id` + policy.

## 11. Hal yang Perlu Diverifikasi/Diputuskan

- Apakah atlet perlu "menerima" undangan (pending → active) atau langsung terhubung?
- Apakah coach boleh melihat data atlet yang belum aktif (pending)?
- Notifikasi ke atlet saat coach assign plan? (bisa via rekomendasi/dashboard)
- Batas jumlah atlet per coach (untuk kinerja chart).

## 12. Catatan Teknis

- Semua fitur existing memakai `auth()->user()` / `$request->user()` → jangan ubah logika
  inti; hanya TAMBAH lapisan coach yang memakai user target eksplisit.
- Test: gunakan `User::factory()` + seed data atlet (workouts/recovery) lalu assert
  halaman coach menampilkan ringkasan atlet yang terhubung dan 403/redirect untuk yang tidak.
- Setelah implementasi: `php artisan test` hijau, `php artisan view:cache`, push → CI →
  auto-deploy (sudah berfungsi).

---

# Lampiran — Riset Pasar Indonesia (Coach CRM)

> Draft riset — v2 diperbarui 2026-08-28. **Dokumen hidup**: angka & kesimpulan
> masih dilengkapi (riset lanjutan direncanakan). Untuk keputusan strategis;
> validasi lapangan (Fase 0) tetap wajib.

## 13. Apakah sudah ada "coach-CRM" lari di Indonesia?

**Tidak ada yang khusus.** Terdekat, dari paling relevan:

| Pemain | Apa itu | Overlap | Kenapa bukan coach-CRM |
|---|---|---|---|
| **Ruang Lari** (ruanglari.com) | Positioning: *"marketplace training plan + online coaching pertama & terbaik di Indonesia"*. AI plan generator (VDOT), chat coach + evaluasi berkala di program premium, komunitas, event, pacer finder. | **Tinggi** — sudah punya relasi coach–atlet, chat, evaluasi, plan delivery, **plus distribusi** (komunitas lari terbesar). | B2C-first (atlet beli program); coach = sisi supply, bukan pengguna alat bisnisnya. Tak ada dashboard roster/kepatuhan untuk coach mengelola bisnisnya. Tak berbasis Garmin. |
| **perlarian.id** | "One platform for every running step": cari event + **AI coaching** + komunitas. | Sedang — bersaing di angle "AI coaching" & mindshare. | Direct-to-consumer AI, bukan alat untuk human coach. |
| **FitCenter.id** | Aplikasi Indonesia untuk **personal trainer** kelola klien: penjadwalan, catat progres, data klien terpusat. | Konsep CRM-nya paling mirip. | Konteks gym/strength, bukan endurance/lari/trail, bukan Garmin-native, tanpa analitik recovery/training-load. |
| RunPlan, Fitness Indonesia, Edumisi, Edupoint, Superprof (74 coach lari terdaftar) | Jasa/program coaching (manusia). | — | Bukan produk software. |
| **Arduua** (Swedia, ada halaman ID) | Jasa coaching trail/ultra manusia + bundling TrainingPeaks Premium + komunitas global. | Bersaing untuk trail runner yang mau coaching. | Bukan produk Indonesia, bukan CRM. |
| Strava, MUFIT, FIT HUB, STRONGBEE, Halodoc/Alodokter | Tracker / gym / telemedicine. | Rendah. | Kategori berbeda. |

**Alat yang benar-benar dipakai coach lari Indonesia sekarang:** WhatsApp + Google Sheets,
dan **TrainingPeaks** (mis. coach "Andy Nurman/abay"). Sebagian di TrueCoach/Trainerize —
semuanya USD, Inggris, wajib kartu.

> Niche spesifik *"CRM + AI untuk coach lari/trail berbasis Garmin, Bahasa Indonesia,
> pembayaran lokal"* = **kosong**. Tapi ruang lebih luas ("bantu pelari latihan
> terstruktur / AI / dengan coach") **sudah ramai**, dan Ruang Lari sudah memegang
> posisi platform komunitas + distribusi. "Gap" ≠ pasti laku.

## 14. Sinyal permintaan — KUAT di sisi pelari

- **Ledakan lari**: pelari aktif **56 rb (Jan 2024) → 242 rb (Mei 2025)**, ~+330%.
  Klub di Strava **6× lipat** pada 2025. **600+ event** lari (road+trail) setahun.
  Jakarta Running Festival: 1.600 → **27.000** peserta. Jakarta International Marathon
  2025 **31 rb+**, Maybank Marathon Bali **13.200**.
- **Trail matang**: **Indonesia Trail Run Series** dibentuk 2025 (5 event nasional
  bersatu, dorongan sport tourism); BTS 100, Mantra 116, Dieng Trail Run (edisi ke-4);
  komunitas daerah (Trail Runners Yogyakarta, Malang Trail Runners).
- **Permintaan coaching naik** ("jasa pelatih kian diminati"). Klien inti: profesional
  mapan, waktu terbatas, mau latihan terstruktur & science-based.
- **Garmin cocok dengan segmen ini**: vendor smartwatch **#2** di Indonesia; Garmin+Huawei
  **>80%** di segmen Rp2–3 jt; brand dominan untuk pengguna olahraga serius — yaitu orang
  yang paling mungkin menyewa coach.
- Pasar fitness & wellness digital Indonesia diproyeksikan **~USD 2,23 miliar pada 2027**.
- **Friksi alat internasional**: TrainingPeaks Coach Edition **$21,99–54,99/bln, USD,
  wajib kartu** — tak ada QRIS/e-wallet, forex, Inggris; Sheets/WA tak punya analitik
  Garmin dalam. Celah nyata.

## 15. Sinyal HATI-HATI — lemah di sisi coach (pembeli CRM)

- **Pembeli coach-CRM = coach, bukan pelari.** Jumlah coach lari/endurance Indonesia yang
  (a) punya cukup atlet berbayar sampai *butuh* software dan (b) mau bayar SaaS bulanan =
  **kecil** — kemungkinan ratusan s/d rendah-ribuan. Mayoritas "coach" = pacer hobi /
  leader run-club, bukan bisnis.
- **WTP B2B SaaS di usaha mikro Indonesia rendah** — banyak tetap pakai WhatsApp+Sheets gratis.
- **Startup health/fitness Indonesia sulit menggalang dana** (regulasi, kebiasaan pasar).
- **Ruang Lari sudah menguasai** posisi platform komunitas + relasi coach + distribusi.
- **Risiko Garmin API tidak resmi** (`garminconnect` scraping) — makin parah di skala komersial.
- **Friksi dua sisi**: butuh coach *dan* atletnya di RAGA (atlet sambungkan Garmin sendiri).

## 16. Ekonomi / sanity check WTP

Coach online menagih atlet **~Rp600 rb–1,5 jt/bln** (Fitness Indonesia: Rp1,2 jt/2 bln;
paket bulanan "jutaan"). Coach dengan 5–10 atlet = Rp3–15 jt/bln omzet. Bayar software
**Rp100–200 rb/bln** masuk akal *kalau* jelas menghemat waktu / memenangkan klien.
TrainingPeaks (~Rp360 rb–900 rb/bln) sudah jadi acuan yang dibayar coach paling
profesional → ada "payung harga", tapi hanya untuk lapisan tipis itu.

## 17. Pembacaan realistis & rekomendasi

- **Pasar "jual software ke coach lari Indonesia" tipis hari ini.** Lebih kuat jangka pendek:
  (a) lapisan coach-assist di atas produk **runner-first**, atau (b) model **marketplace**
  (seperti Ruang Lari) di mana CRM jadi produk sampingan.
- **Alternatif target**: coach berbahasa Inggris melayani atlet trail global — kedalaman
  Garmin+AI RAGA bersaing langsung dengan TrainingPeaks/Arduua, WTP dalam USD lebih tinggi.
  Indonesia jadi pasar validasi, bukan pasar utama.
- **Trail niche nyata & underserved** tapi **kecil secara absolut** — bagus sebagai wedge
  fokus, bukan pasar besar berdiri sendiri.
- **Bangun & test di codebase ini boleh (Fase 1–3)**, tapi sebelum onboard coach eksternal
  pertama, berdirikan **instance terpisah** (DB + domain sendiri, kode sama). Jangan campur
  data pelanggan dengan instance personal.

## 18. Posisi & pembeda RAGA vs kompetitor

| Kemampuan | RAGA (target) | Kompetitor umum |
|---|---|---|
| Data Garmin native | ✅ otomatis | Banyak input manual / wearable lain |
| AI Coach per-atlet | ✅ terintegrasi | Umumnya belum sekuat ini |
| Fokus trail/running + analytics dalam | ✅ | Umum |
| Bahasa Indonesia + pembayaran lokal | ✅ | Umumnya Inggris + USD/kartu |
| Harga | Terjangkau (Rp) | $15–55/bln per coach |
| Distribusi / komunitas | ❌ belum ada | Ruang Lari & Strava sudah kuat |

## 19. Keputusan strategis yang perlu diambil

- [ ] **Model**: SaaS untuk coach lokal vs coach-assist di produk runner-first vs marketplace
      vs target coach global berbahasa Inggris.
- [ ] **Garmin**: tetap pakai `garminconnect` (personal-scale) atau ajukan Garmin Health API
      resmi (syarat untuk komersial multi-user). Penentu kelayakan CRM berbayar.
- [ ] Freemium: batas atlet di tier gratis? (saran: 3–5)
- [ ] Harga tier berbayar (saran awal: Rp 50rb–150rb/bln per coach tergantung fitur)
- [ ] Target awal untuk validasi: komunitas trail (Yogya/Malang), coach TrainingPeaks ID
- [ ] Produk **terpisah** dari RAGA personal (data/codebase/instance) sebelum ada pelanggan nyata

## 20. Sumber

- Hypeabis — Ekosistem Lari Makin Matang, Jasa Pelatih Kian Diminati
- GoodStats — Peningkatan Tren Lari di Indonesia (56rb→242rb pelari)
- Indonesia Marathon — Kalender Lomba Lari 2025 (600+ event)
- IDN Times — Jakarta Running Festival 2025: rekor peserta
- Kompas Tekno — Strava jelang JRF 2025 (klub 6× lipat)
- Harian Merapi — Indonesia Trail Run Series resmi dibentuk
- IDN Times — 7 Event Trail Run Terbesar di Indonesia
- ANTARA — smartwatch Indonesia (pangsa Garmin #2, Garmin+Huawei >80% segmen Rp2–3jt)
- ruanglari.com (/ dan /programs); perlarian.id/coaching; fitcenter.id/aplikasi/untuk-personal-trainer; arduua.com/id
- TrainingPeaks — Coach Account Pricing & Billing ($21,99–54,99/bln USD)
- Superprof, Edumisi, Fitness Indonesia — tarif coaching lari Indonesia
- Liputan6 — FitHappy raih pra-seed (East Ventures)

> **TODO riset lanjutan**: sizing TAM coach bersertifikat (data.go.id punya dataset
> "jumlah pelatih olahraga bersertifikat"); apakah Ruang Lari / perlarian integrasi Garmin;
> syarat & biaya Garmin Health API resmi; benchmark Coachbox/FinalSurge/Today's Plan;
> wawancara Fase 0.

