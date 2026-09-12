@props(['splits' => [], 'isOwner' => false])

@php
    // Tabel split per kilometer. HR hanya ditampilkan ke pemilik aktivitas
    // (data detak jantung = data kesehatan pribadi).
    $showHeartRate = $isOwner && collect($splits)->contains(fn (array $split): bool => $split['avg_heart_rate'] !== null);

    $formatDuration = static function (int $seconds): string {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $secs)
            : sprintf('%d:%02d', $minutes, $secs);
    };

    $tagMeta = [
        'fastest' => ['label' => 'Tercepat', 'variant' => 'pace'],
        'climb' => ['label' => 'Tanjakan', 'variant' => 'strain'],
        'descent' => ['label' => 'Turunan', 'variant' => 'recovery'],
    ];
@endphp

<div {{ $attributes->merge(['class' => '']) }}>
    @if (count($splits) > 0)
        <div class="overflow-x-auto">
            <table class="w-full min-w-[42rem] border-collapse text-sm">
                <thead>
                    <tr class="border-b border-telemetry-line">
                        <th class="py-2 pr-4 text-left telemetry-label">Split</th>
                        <th class="py-2 pr-4 text-right telemetry-label">Jarak</th>
                        <th class="py-2 pr-4 text-right telemetry-label">Waktu</th>
                        <th class="py-2 pr-4 text-right telemetry-label">Pace</th>
                        <th class="py-2 pr-4 text-right telemetry-label">D+ / D−</th>
                        <th class="py-2 pr-4 text-right telemetry-label">Grade</th>
                        @if ($showHeartRate)
                            <th class="py-2 pr-4 text-right telemetry-label">HR</th>
                        @endif
                        <th class="py-2 text-right telemetry-label">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($splits as $split)
                        <tr class="border-b border-telemetry-line/70 {{ $split['tag'] === 'fastest' ? 'bg-telemetry-well' : '' }}">
                            <td class="py-2.5 pr-4">
                                <span class="telemetry-value text-base">{{ str_pad((string) $split['index'], 2, '0', STR_PAD_LEFT) }}</span>
                            </td>
                            <td class="py-2.5 pr-4 text-right telemetry-value text-sm">{{ number_format($split['distance_km'], 2) }}<span class="text-[10px] text-telemetry-slate"> km</span></td>
                            <td class="py-2.5 pr-4 text-right telemetry-value text-sm">{{ $formatDuration($split['duration_seconds']) }}</td>
                            <td class="py-2.5 pr-4 text-right telemetry-value text-sm">
                                {{ $split['pace_seconds_per_km'] !== null ? sprintf('%d:%02d', intdiv($split['pace_seconds_per_km'], 60), $split['pace_seconds_per_km'] % 60) : '--' }}
                            </td>
                            <td class="py-2.5 pr-4 text-right text-sm font-medium text-telemetry-slate">
                                +{{ number_format($split['elevation_gain']) }} / −{{ number_format($split['elevation_loss']) }}
                            </td>
                            <td class="py-2.5 pr-4 text-right text-sm font-semibold {{ $split['avg_grade'] > 0 ? 'text-telemetry-ember-deep' : 'text-telemetry-chrono-deep' }}">
                                {{ $split['avg_grade'] > 0 ? '+' : '' }}{{ number_format($split['avg_grade'], 1) }}%
                            </td>
                            @if ($showHeartRate)
                                <td class="py-2.5 pr-4 text-right text-sm text-telemetry-slate">{{ $split['avg_heart_rate'] !== null ? round($split['avg_heart_rate']).' bpm' : '--' }}</td>
                            @endif
                            <td class="py-2.5 text-right">
                                @if ($split['tag'] !== null && isset($tagMeta[$split['tag']]))
                                    <x-chip :variant="$tagMeta[$split['tag']]['variant']">{{ $tagMeta[$split['tag']]['label'] }}</x-chip>
                                @else
                                    <span class="telemetry-label">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="mt-3 text-[11px] text-telemetry-slate">
            Split dihitung dari jarak per sampel (GPS, atau pace bila GPS tidak ada).
            Catatan menandai split tercepat, tanjakan terbesar, dan turunan terbesar.
            @unless ($isOwner)
                Kolom detak jantung hanya terlihat oleh pemilik aktivitas.
            @endunless
        </p>
    @else
        <p class="py-6 text-center text-sm text-telemetry-slate">Belum ada data yang cukup untuk menghitung split per kilometer.</p>
    @endif
</div>
