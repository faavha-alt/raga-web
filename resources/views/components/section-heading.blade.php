@props(['title', 'hint' => null])

{{-- Judul seksi gaya telemetry: label uppercase ber-tracking lebar, dengan
     keterangan kecil opsional di sisi kanan (mis. "7 HARI TERAKHIR"). --}}
<div class="mb-3 flex items-end justify-between gap-4">
    <h3 class="telemetry-label-lg text-telemetry-ink">{{ $title }}</h3>
    @if ($hint)
        <p class="telemetry-label">{{ $hint }}</p>
    @endif
</div>
