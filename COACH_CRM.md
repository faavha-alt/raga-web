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
