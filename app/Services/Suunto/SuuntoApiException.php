<?php

namespace App\Services\Suunto;

use RuntimeException;

/**
 * Kegagalan yang bisa dimengerti pengguna saat berkomunikasi dengan Suunto
 * Cloud API (konfigurasi kosong, token kedaluwarsa, HTTP error, respons tak
 * terduga). Pesannya disimpan di `suunto_connections.last_sync_message` supaya
 * muncul di halaman Settings, bukan hanya di log.
 */
class SuuntoApiException extends RuntimeException {}
