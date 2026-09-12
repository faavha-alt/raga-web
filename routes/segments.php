<?php

use App\Http\Controllers\SegmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute segment & leaderboard
|--------------------------------------------------------------------------
|
| File ini di-require dari routes/web.php di dalam grup ['auth', 'verified'].
|
| Nama rute yang sudah disepakati:
|   segments.index    GET    /segments
|   segments.create   GET    /segments/create
|   segments.store    POST   /segments
|   segments.show     GET    /segments/{segment}
|   segments.destroy  DELETE /segments/{segment}
|   segments.rescan   POST   /segments/{segment}/rescan
|
*/

Route::get('/segments', [SegmentController::class, 'index'])->name('segments.index');
Route::get('/segments/create', [SegmentController::class, 'create'])->name('segments.create');
Route::post('/segments', [SegmentController::class, 'store'])->name('segments.store');
Route::get('/segments/{segment}', [SegmentController::class, 'show'])->name('segments.show');
Route::delete('/segments/{segment}', [SegmentController::class, 'destroy'])->name('segments.destroy');

// Pemindaian sinkron memuat sampel hingga 500 aktivitas kandidat dan tidak
// memakai queue worker, jadi rescan dibatasi 5x per menit per pengguna agar
// tidak bisa dipakai untuk membebani server berulang kali.
Route::post('/segments/{segment}/rescan', [SegmentController::class, 'rescan'])
    ->middleware('throttle:5,1')
    ->name('segments.rescan');
