{{--
    Halaman offline publik & mandiri.
    Tidak memakai app shell layout, tidak butuh login, dan tidak memuat data
    pengguna apa pun. Dipakai oleh public/sw.js sebagai fallback navigasi.
    Route yang dibutuhkan (di luar grup auth): Route::view('/offline', 'offline')->name('offline');
--}}
<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <meta name="theme-color" content="#21A08C">
        <title>Offline — RAGA</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('icons/icon.svg') }}">
        <style>
            :root {
                color-scheme: light;
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
                font-family: "Plus Jakarta Sans", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
                background-color: #f8fafc;
                color: #0f172a;
            }

            .card {
                width: 100%;
                max-width: 420px;
                background: #ffffff;
                border: 1px solid #f1f5f9;
                border-radius: 28px;
                padding: 40px 32px;
                text-align: center;
                box-shadow: 0 18px 40px -24px rgba(16, 24, 40, 0.35);
            }

            .badge {
                width: 64px;
                height: 64px;
                margin: 0 auto 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 20px;
                font-size: 28px;
                color: #ffffff;
                background: linear-gradient(135deg, #21A08C 0%, #6C5CE7 100%);
            }

            h1 {
                margin: 0 0 8px;
                font-size: 22px;
                font-weight: 800;
            }

            p {
                margin: 0 0 24px;
                font-size: 14px;
                line-height: 1.6;
                color: #64748b;
            }

            button {
                width: 100%;
                border: 0;
                cursor: pointer;
                border-radius: 999px;
                padding: 14px 24px;
                font-size: 14px;
                font-weight: 700;
                font-family: inherit;
                color: #ffffff;
                background: linear-gradient(90deg, #21A08C 0%, #6C5CE7 100%);
            }

            button:active {
                transform: scale(0.98);
            }

            .hint {
                margin: 16px 0 0;
                font-size: 12px;
                color: #94a3b8;
            }
        </style>
    </head>
    <body>
        <main class="card">
            <div class="badge">📡</div>
            <h1>Kamu sedang offline</h1>
            <p>
                RAGA tidak bisa memuat data terbaru tanpa koneksi internet.
                Periksa jaringanmu, lalu coba lagi.
            </p>
            <button type="button" onclick="window.location.reload()">Coba lagi</button>
            <p class="hint">Halaman berisi data kesehatan tidak pernah disimpan di cache perangkat ini.
                Rekaman GPS yang belum selesai dikirim disimpan sementara di perangkat ini sampai berhasil diunggah —
                hapus rekaman itu bila memakai perangkat bersama.</p>
        </main>
    </body>
</html>
