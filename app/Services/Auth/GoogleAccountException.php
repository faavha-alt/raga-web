<?php

namespace App\Services\Auth;

use RuntimeException;

/**
 * Kegagalan yang bisa dijelaskan ke pengguna saat masuk lewat Google.
 *
 * Dilempar terpisah dari error tak terduga supaya controller bisa menampilkannya
 * sebagai pesan di halaman login, bukan sebagai halaman error 500.
 */
class GoogleAccountException extends RuntimeException {}
