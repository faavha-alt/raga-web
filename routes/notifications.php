<?php

/*
|--------------------------------------------------------------------------
| Rute notifikasi
|--------------------------------------------------------------------------
|
| File ini di-require dari routes/web.php di dalam grup ['auth', 'verified'].
|
| Nama rute yang sudah disepakati:
|   notifications.index    GET  /notifications
|   notifications.read     POST /notifications/{notification}/read
|   notifications.readAll  POST /notifications/read-all
|
*/

use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');

// Didaftarkan sebelum rute {notification} agar "read-all" tidak dianggap id.
Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');

Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
