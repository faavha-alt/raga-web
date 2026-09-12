@props(['profile', 'height' => 'h-40'])

@php
    // Grafik profil elevasi dengan pewarnaan per grade. Segmen diwarnai sesuai
    // kemiringan, bukan gradien halus, supaya tanjakan/turunan langsung terbaca
    // (dan tetap konsisten dengan aturan DESIGN.md: chart tanpa gradient fill).
    $points = $profile['points'] ?? [];
    $available = ($profile['available'] ?? false) && count($points) > 1;

    $bands = [
        ['label' => '≥ 5% naik', 'color' => '#FF3E1D', 'test' => fn (float $g): bool => $g >= 5],
        ['label' => '0–5% naik', 'color' => '#F59E0B', 'test' => fn (float $g): bool => $g > 0 && $g < 5],
        ['label' => '0–5% turun', 'color' => '#0070F3', 'test' => fn (float $g): bool => $g <= 0 && $g > -5],
        ['label' => '≥ 5% turun', 'color' => '#1D4ED8', 'test' => fn (float $g): bool => $g <= -5],
    ];

    $colorFor = static function (float $grade) use ($bands): string {
        foreach ($bands as $band) {
            if ($band['test']($grade)) {
                return $band['color'];
            }
        }

        return '#0070F3';
    };

    if ($available) {
        $totalKm = max(0.001, (float) $profile['total_distance_km']);
        $min = (float) $profile['min_elevation'];
        $max = (float) $profile['max_elevation'];
        $range = max(1.0, $max - $min);

        $width = 100.0;
        $heightUnits = 40.0;
        $padTop = 4.0;
        $baseline = $heightUnits - 4.0;

        $coords = array_map(static function (array $point) use ($totalKm, $min, $range, $width, $padTop, $baseline): array {
            $x = min($width, ($point['distance_km'] / $totalKm) * $width);
            $y = $baseline - ((($point['elevation'] - $min) / $range) * ($baseline - $padTop));

            return ['x' => round($x, 2), 'y' => round($y, 2), 'grade' => (float) $point['grade']];
        }, $points);
    }
@endphp

<div {{ $attributes->merge(['class' => '']) }}>
    @if ($available)
        <svg viewBox="0 0 100 40" preserveAspectRatio="none" class="{{ $height }} w-full" role="img" aria-label="Profil elevasi dengan warna kemiringan">
            @for ($i = 0; $i < count($coords) - 1; $i++)
                <line
                    x1="{{ $coords[$i]['x'] }}"
                    y1="{{ $coords[$i]['y'] }}"
                    x2="{{ $coords[$i + 1]['x'] }}"
                    y2="{{ $coords[$i + 1]['y'] }}"
                    stroke="{{ $colorFor($coords[$i + 1]['grade']) }}"
                    stroke-width="1.6"
                    vector-effect="non-scaling-stroke"
                />
            @endfor
        </svg>

        <div class="mt-3 grid grid-cols-2 gap-4 border-t border-telemetry-line pt-3 sm:grid-cols-4">
            <div>
                <p class="telemetry-label">Naik (D+)</p>
                <p class="mt-1 telemetry-value text-lg">{{ number_format($profile['gain_meters']) }}<span class="text-xs"> m</span></p>
            </div>
            <div>
                <p class="telemetry-label">Turun (D−)</p>
                <p class="mt-1 telemetry-value text-lg">{{ number_format($profile['loss_meters']) }}<span class="text-xs"> m</span></p>
            </div>
            <div>
                <p class="telemetry-label">Elevasi Min</p>
                <p class="mt-1 telemetry-value text-lg">{{ number_format($profile['min_elevation']) }}<span class="text-xs"> m</span></p>
            </div>
            <div>
                <p class="telemetry-label">Elevasi Maks</p>
                <p class="mt-1 telemetry-value text-lg">{{ number_format($profile['max_elevation']) }}<span class="text-xs"> m</span></p>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2">
            @foreach ($bands as $band)
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-0.5 w-4" style="background-color: {{ $band['color'] }}"></span>
                    <span class="telemetry-label">{{ $band['label'] }}</span>
                </span>
            @endforeach
        </div>
    @else
        <p class="py-6 text-center text-sm text-telemetry-slate">Tidak ada data elevasi untuk aktivitas ini.</p>
    @endif
</div>
