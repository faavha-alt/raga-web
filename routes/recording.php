<?php

/*
|--------------------------------------------------------------------------
| Rute perekaman aktivitas GPS dari browser
|--------------------------------------------------------------------------
|
| Direkam di sisi klien dengan Geolocation API lalu dikirim ke server untuk
| dihitung (jarak, pace, elevasi, HR zone) dan disimpan sebagai Workout +
| WorkoutSample, sehingga RAGA bisa dipakai tanpa Garmin.
|
| File ini di-require dari routes/web.php di dalam grup ['auth', 'verified'].
|
| Nama rute yang sudah disepakati:
|   record.index       GET  /record
|   recordings.store   POST /recordings
|
*/

use App\Http\Controllers\RecordingController;
use Illuminate\Support\Facades\Route;

Route::get('/record', [RecordingController::class, 'index'])->name('record.index');

// Throttle 20 permintaan/menit per pengguna: satu aktivitas hanya butuh satu
// POST (plus beberapa retry bila gagal), jadi 20 masih sangat longgar untuk
// pemakaian wajar, tetapi membendung loop/abuse yang mengirim payload
// 100.000 titik berulang kali hingga menghabiskan memori/disk.
Route::post('/recordings', [RecordingController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('recordings.store');
