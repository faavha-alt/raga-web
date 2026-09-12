@props(['pillars' => []])

@php
    // Radar 5 sumbu tanpa library JS. Skor 0–100 digambar pada radius
    // proporsional; pilar bernilai null TIDAK ikut digambar sebagai 0 — hanya
    // labelnya yang muncul dengan "--", dan poligon dibentuk dari pilar yang
    // benar-benar punya data.
    $n = count($pillars);
    $cx = 50.0;
    $cy = 50.0;
    $radius = 34.0;

    $shortLabels = [
        'endurance' => 'END',
        'aerobic' => 'AER',
        'recovery' => 'REC',
        'readiness' => 'RDY',
        'balance' => 'LOAD',
    ];

    $axes = [];
    foreach (array_values($pillars) as $i => $pillar) {
        $angle = deg2rad(-90 + ($i * 360 / max(1, $n)));
        $axes[] = [
            'pillar' => $pillar,
            'cos' => cos($angle),
            'sin' => sin($angle),
        ];
    }

    $ringPolygons = [];
    foreach ([0.25, 0.5, 0.75, 1.0] as $scale) {
        $points = [];
        foreach ($axes as $axis) {
            $points[] = round($cx + $axis['cos'] * $radius * $scale, 2).','.round($cy + $axis['sin'] * $radius * $scale, 2);
        }
        $ringPolygons[] = implode(' ', $points);
    }

    $dataPoints = [];
    $dots = [];
    foreach ($axes as $axis) {
        $score = $axis['pillar']['score'];
        if ($score === null) {
            continue;
        }

        $x = $cx + $axis['cos'] * $radius * ((float) $score / 100);
        $y = $cy + $axis['sin'] * $radius * ((float) $score / 100);
        $dataPoints[] = round($x, 2).','.round($y, 2);
        $dots[] = ['x' => round($x, 2), 'y' => round($y, 2)];
    }

    $hasShape = count($dataPoints) >= 3;
    $hasAnyScore = $dataPoints !== [];
@endphp

<div {{ $attributes->merge(['class' => '']) }}>
    @if ($hasAnyScore)
        <div class="flex justify-center">
            <svg viewBox="0 0 100 106" class="h-56 w-full max-w-[340px]" role="img" aria-label="Radar 5 pilar fisiologis">
                {{-- Cincin skala 25/50/75/100 --}}
                @foreach ($ringPolygons as $ring)
                    <polygon points="{{ $ring }}" fill="none" stroke="#E2E8F0" stroke-width="1" vector-effect="non-scaling-stroke" />
                @endforeach

                {{-- Sumbu + label pilar --}}
                @foreach ($axes as $axis)
                    @php
                        $endX = $cx + $axis['cos'] * $radius;
                        $endY = $cy + $axis['sin'] * $radius;
                        $labelX = $cx + $axis['cos'] * ($radius + 6);
                        $labelY = $cy + $axis['sin'] * ($radius + 6);
                        $anchor = $axis['cos'] > 0.15 ? 'start' : ($axis['cos'] < -0.15 ? 'end' : 'middle');
                        $isNull = $axis['pillar']['score'] === null;
                    @endphp
                    <line
                        x1="{{ $cx }}" y1="{{ $cy }}" x2="{{ round($endX, 2) }}" y2="{{ round($endY, 2) }}"
                        stroke="{{ $isNull ? '#DEE2E6' : '#CBD5E1' }}"
                        stroke-width="1"
                        stroke-dasharray="{{ $isNull ? '2 2' : '' }}"
                        vector-effect="non-scaling-stroke"
                    />
                    <text
                        x="{{ round($labelX, 2) }}" y="{{ round($labelY, 2) }}"
                        text-anchor="{{ $anchor }}"
                        dominant-baseline="middle"
                        font-size="4.2"
                        font-weight="700"
                        letter-spacing="0.4"
                        fill="{{ $isNull ? '#ADB5BD' : '#495057' }}"
                    >{{ $shortLabels[$axis['pillar']['key']] ?? mb_strtoupper(mb_substr($axis['pillar']['label'], 0, 4)) }}</text>
                    <text
                        x="{{ round($labelX, 2) }}" y="{{ round($labelY + 4.6, 2) }}"
                        text-anchor="{{ $anchor }}"
                        dominant-baseline="middle"
                        font-size="4.6"
                        font-weight="700"
                        fill="{{ $isNull ? '#ADB5BD' : '#0D1117' }}"
                    >{{ $isNull ? '--' : $axis['pillar']['score'] }}</text>
                @endforeach

                {{-- Poligon data --}}
                @if ($hasShape)
                    <polygon
                        points="{{ implode(' ', $dataPoints) }}"
                        fill="rgba(255, 62, 29, 0.12)"
                        stroke="#FF3E1D"
                        stroke-width="1.5"
                        vector-effect="non-scaling-stroke"
                    />
                @endif

                @foreach ($dots as $dot)
                    <circle cx="{{ $dot['x'] }}" cy="{{ $dot['y'] }}" r="1.4" fill="#FF3E1D" />
                @endforeach
            </svg>
        </div>
    @else
        <div class="flex h-56 items-center justify-center border border-dashed border-telemetry-line bg-telemetry-well px-6 text-center">
            <p class="text-sm text-telemetry-slate">
                Belum ada pilar yang bisa dihitung — butuh aktivitas, skor recovery/readiness,
                VO2max, atau ACWR. Radar tidak digambar supaya tidak terlihat seperti angka nol.
            </p>
        </div>
    @endif

    {{-- Sumber tiap pilar ditulis terbuka supaya angkanya bisa diaudit. --}}
    <ul class="mt-4 space-y-1.5 border-t border-telemetry-line pt-3">
        @foreach ($pillars as $pillar)
            <li class="flex items-baseline justify-between gap-3">
                <span class="text-[11px] text-telemetry-slate">
                    <span class="font-bold text-telemetry-ink">{{ $pillar['label'] }}</span>
                    — {{ $pillar['source'] }}
                </span>
                <span class="telemetry-value shrink-0 text-sm">{{ $pillar['score'] ?? '--' }}</span>
            </li>
        @endforeach
    </ul>
</div>
