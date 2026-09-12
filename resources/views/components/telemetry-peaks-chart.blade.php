@props(['series' => [], 'caption' => null, 'reference' => null])

@php
    // Grafik puncak beban bergaya telemetry: area + garis, tanpa gradien dan
    // tanpa library JS. Skala dihitung dari nilai maksimum seri supaya puncak
    // selalu menyentuh bagian atas kotak.
    $values = array_map(static fn (array $point): float => (float) ($point['value'] ?? 0), $series);
    $count = count($values);
    $max = $count > 0 ? max($values) : 0.0;
    $scaleMax = $max > 0 ? $max : 1.0;

    $width = 100.0;
    $height = 34.0;
    $padTop = 5.0;
    $baseline = $height - 3.0;

    $points = [];
    foreach ($values as $index => $value) {
        $x = $count > 1 ? ($index / ($count - 1)) * $width : $width / 2;
        $y = $baseline - (($value / $scaleMax) * ($baseline - $padTop));
        $points[] = round($x, 2).','.round($y, 2);
    }

    $areaPath = $points === []
        ? ''
        : 'M0,'.$baseline.' L'.implode(' L', $points).' L'.$width.','.$baseline.' Z';

    $referenceY = $reference !== null
        ? $baseline - ($reference * ($baseline - $padTop))
        : null;
@endphp

<div {{ $attributes->merge(['class' => '']) }}>
    @if ($count > 0)
        <svg viewBox="0 0 {{ $width }} {{ $height }}" preserveAspectRatio="none" class="h-24 w-full" role="img" aria-label="Grafik puncak beban 7 hari terakhir">
            @if ($referenceY !== null)
                <line x1="0" y1="{{ $referenceY }}" x2="{{ $width }}" y2="{{ $referenceY }}" stroke="#CBD5E1" stroke-width="1" stroke-dasharray="3 3" vector-effect="non-scaling-stroke" />
            @endif
            <path d="{{ $areaPath }}" fill="rgba(255, 62, 29, 0.08)" />
            <polyline points="{{ implode(' ', $points) }}" fill="none" stroke="#FF3E1D" stroke-width="1.5" vector-effect="non-scaling-stroke" />
        </svg>

        <div class="mt-2 flex justify-between">
            @foreach ($series as $point)
                <span class="telemetry-label">{{ $point['label'] }}</span>
            @endforeach
        </div>
    @else
        <p class="py-8 text-center text-sm text-telemetry-slate">Belum ada data untuk digambarkan.</p>
    @endif

    @if ($caption)
        <p class="mt-2 text-[11px] text-telemetry-slate">{{ $caption }}</p>
    @endif
</div>
