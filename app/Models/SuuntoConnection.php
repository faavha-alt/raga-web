<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Koneksi akun Suunto App milik satu pengguna.
 *
 * Berbeda dari `GarminConnection` (yang hanya menandai "sudah login" karena
 * token Garmin disimpan sebagai file oleh skrip Python), di sini token OAuth
 * Suunto disimpan di database dalam bentuk terenkripsi — tidak ada berkas
 * rahasia di disk dan tidak ada proses eksternal yang perlu dijalankan.
 */
#[Fillable([
    'user_id',
    'suunto_username',
    'access_token',
    'refresh_token',
    'expires_at',
    'scope',
    'connected_at',
    'last_synced_at',
    'last_sync_status',
    'last_sync_message',
])]
class SuuntoConnection extends Model
{
    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Token dianggap perlu di-refresh 60 detik sebelum kedaluwarsa. */
    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->lte(now()->addMinute());
    }
}
