<?php

/*
|--------------------------------------------------------------------------
| Rute sosial — feed, follow, kudos, komentar, profil atlet
|--------------------------------------------------------------------------
|
| File ini di-require dari routes/web.php di dalam grup middleware
| ['auth', 'verified'], jadi setiap rute di sini otomatis butuh login.
|
| Nama rute yang sudah disepakati (dipakai navigasi & view lain):
|   feed                      GET    /feed
|   explore                   GET    /explore
|   athletes.show             GET    /@{user:username}
|   athletes.followers        GET    /athletes/{user}/followers
|   athletes.following        GET    /athletes/{user}/following
|   follows.store             POST   /athletes/{user}/follow
|   follows.destroy           DELETE /athletes/{user}/follow
|   kudos.store               POST   /activities/{workout}/kudos
|   kudos.destroy             DELETE /activities/{workout}/kudos
|   comments.store            POST   /activities/{workout}/comments
|   comments.destroy          DELETE /comments/{comment}
|   activities.visibility.update PATCH /activities/{workout}/visibility
|
| Privasi: setiap aksi pada aktivitas WAJIB lewat scope Workout::visibleTo()
| dan mengembalikan 404 (bukan 403) untuk aktivitas yang tidak boleh dilihat.
|
*/

use App\Http\Controllers\ActivityVisibilityController;
use App\Http\Controllers\AthleteController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\KudosController;
use Illuminate\Support\Facades\Route;

Route::get('/feed', [FeedController::class, 'index'])->name('feed');
Route::get('/explore', [ExploreController::class, 'index'])->name('explore');

// Pengguna tanpa username tidak bisa dijangkau lewat /@... (bind by username).
Route::get('/@{user:username}', [AthleteController::class, 'show'])->name('athletes.show');
Route::get('/athletes/{user}/followers', [AthleteController::class, 'followers'])->name('athletes.followers');
Route::get('/athletes/{user}/following', [AthleteController::class, 'following'])->name('athletes.following');

Route::post('/athletes/{user}/follow', [FollowController::class, 'store'])->name('follows.store');
Route::delete('/athletes/{user}/follow', [FollowController::class, 'destroy'])->name('follows.destroy');

Route::post('/activities/{workout}/kudos', [KudosController::class, 'store'])->name('kudos.store');
Route::delete('/activities/{workout}/kudos', [KudosController::class, 'destroy'])->name('kudos.destroy');

Route::post('/activities/{workout}/comments', [CommentController::class, 'store'])->name('comments.store');
Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

Route::patch('/activities/{workout}/visibility', [ActivityVisibilityController::class, 'update'])->name('activities.visibility.update');
