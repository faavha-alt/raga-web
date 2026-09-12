# Kontrak UI — Swiss Telemetry Sport (untuk restyle Blade)

Satu-satunya acuan saat menyamakan tampilan semua halaman dengan dashboard.
Spesifikasi lengkap: `acuan_tampilan/swiss_telemetry_sport/DESIGN.md`.
Token warna/font: `tailwind.config.js` (`colors.telemetry.*`, `font-display`).
Halaman acuan yang SUDAH benar: `resources/views/dashboard/index.blade.php`,
`resources/views/activities/show.blade.php`, `resources/views/athlete-dna/index.blade.php`.

## Aturan wajib

1. **Presentasi saja.** Jangan ubah logika PHP, nama variabel, route, `@if`, loop,
   Alpine (`x-data`, `@click`, `x-model`), atribut `id`/`name`/`wire:*`, atau teks
   yang di-assert test. Hanya kelas CSS, markup wrapper, dan ikon/emoji.
2. **Light mode saja.** Hapus semua varian `dark:` (tidak ada toggle dark).
   Jangan tambah `dark:` baru.
3. **Tanpa shadow.** Kartu memakai outline, bukan bayangan. Hanya overlay/menu
   yang boleh `shadow-overlay`. Jangan pakai `shadow-*` lain, jangan gradient
   `bg-mesh`/`bg-gradient-*` untuk kartu.
4. **Radius disiplin**: kartu/komponen `rounded` (4px) atau `rounded-lg` (8px).
   Jangan `rounded-full` untuk chip/badge/tombol; pakai kotak mikro.
5. **Angka = tabular Space Grotesk** lewat kelas `.telemetry-value`.
   Label mikro uppercase = `.telemetry-label` (10px) / `.telemetry-label-lg`.
   Prosa/body tetap `font-sans` (Inter) default.

## Komponen siap pakai (pakai ini, jangan bikin ulang)

| Komponen | Guna |
| --- | --- |
| `<x-card>` | kontainer putih + border `telemetry-line`, padding 6 |
| `<x-metric-tile label=".." value=".." unit=".." />` | sel metrik besar |
| `<x-section-heading title=".." hint=".." />` | judul seksi + keterangan kanan |
| `<x-chip variant="recovery\|pace\|strain\|neutral">` | badge status mikro |
| `<x-split-table :splits :isOwner>` | tabel split |
| `<x-primary-button>` / `<x-secondary-button>` / `<x-danger-button>` | aksi |

Header halaman: `<x-slot name="header">` berisi
`<h1 class="telemetry-value text-4xl sm:text-5xl">JUDUL</h1>` +
`<p class="mt-2 text-sm font-medium text-telemetry-slate">subjudul</p>`,
opsional chip/status di kanan dengan `flex flex-wrap items-end justify-between gap-4`.

## Peta warna (kelas lama → baru)

| Lama | Baru |
| --- | --- |
| `bg-white` | `bg-telemetry-surface` |
| `bg-gray-50`, `bg-gray-100`, `bg-slate-100` | `bg-telemetry-well` |
| `border-gray-200`, `border-gray-300` | `border-telemetry-line` (kuat: `-line-strong`) |
| `text-gray-900`, `text-slate-900` | `text-telemetry-ink` |
| `text-gray-500`, `text-gray-600`, `text-slate-500` | `text-telemetry-slate` |
| `text-gray-400` | `text-telemetry-slate` (label) |
| `divide-gray-*` | `divide-telemetry-line` |
| `rounded-full` (chip/badge/avatar) | `rounded-full` **hanya** untuk avatar; chip → `rounded` |
| `raga-accent` | `telemetry-emerald` |
| `raga-primary` | `telemetry-chrono` |
| `raga-energy` | `telemetry-ember` |
| `raga-excellent` | `telemetry-emerald-deep` |
| `raga-good` | `telemetry-chrono` |
| `raga-moderate` | `telemetry-amber` |
| `raga-low` | `telemetry-ember-deep` |
| `text-gradient`, `bg-mesh` | hapus |

Semantik aksen: **ember** = puncak beban/aksi/kritis, **chrono** = pace/kecepatan,
**emerald** = recovery/sehat, **amber** = peringatan.

## Data table / list

Header tabel: `border-b border-telemetry-line` + `<th class="... telemetry-label">`.
Baris: `border-b border-telemetry-line/70`, hover `hover:bg-telemetry-well`.
Angka rata kanan dengan `telemetry-value`; teks status pakai `<x-chip>`.
Empty state: `<p class="py-6 text-center text-sm text-telemetry-slate">…</p>`.

## File yang TIDAK boleh disentuh

`resources/views/components/**`, `resources/views/layouts/**`, dan `acuan_tampilan/**`
(dimiliki agen lain). Tetap di dalam daftar file yang ditugaskan.
