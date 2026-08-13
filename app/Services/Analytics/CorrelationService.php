<?php

namespace App\Services\Analytics;

/**
 * Pure (no DB) Pearson correlation between two paired daily series, plus a
 * transparent human-facing description. This is a personal-pattern
 * indicator, not proof of causation — describe() intentionally never uses
 * causal wording ("menyebabkan"/"karena") and never editorializes about
 * whether a correlation is "good" or "bad".
 */
class CorrelationService
{
    public const MIN_PAIRED_SAMPLES = 7;

    public const DISCLAIMER = 'Korelasi bukan bukti sebab-akibat — ini hanya pola yang teramati dari data kamu sendiri.';

    /**
     * Inner-joins two {date,value} series on matching dates only.
     *
     * @param  list<array{date: string, value: float}>  $seriesA
     * @param  list<array{date: string, value: float}>  $seriesB
     * @return array{dates: list<string>, x: list<float>, y: list<float>}
     */
    public function align(array $seriesA, array $seriesB): array
    {
        $byDateA = collect($seriesA)->keyBy('date');
        $byDateB = collect($seriesB)->keyBy('date');

        $commonDates = $byDateA->keys()->intersect($byDateB->keys())->sort()->values();

        $dates = [];
        $x = [];
        $y = [];

        foreach ($commonDates as $date) {
            $dates[] = $date;
            $x[] = (float) $byDateA[$date]['value'];
            $y[] = (float) $byDateB[$date]['value'];
        }

        return ['dates' => $dates, 'x' => $x, 'y' => $y];
    }

    /**
     * @param  list<float>  $x
     * @param  list<float>  $y
     * @return array{r: ?float, paired_count: int, sufficient_data: bool, strength: string, direction: string}
     */
    public function pearson(array $x, array $y): array
    {
        $n = min(count($x), count($y));

        if ($n < self::MIN_PAIRED_SAMPLES) {
            return ['r' => null, 'paired_count' => $n, 'sufficient_data' => false, 'strength' => 'insufficient_data', 'direction' => 'none'];
        }

        $meanX = array_sum($x) / $n;
        $meanY = array_sum($y) / $n;

        $numerator = 0.0;
        $sumSqX = 0.0;
        $sumSqY = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $dx = $x[$i] - $meanX;
            $dy = $y[$i] - $meanY;
            $numerator += $dx * $dy;
            $sumSqX += $dx ** 2;
            $sumSqY += $dy ** 2;
        }

        $denominator = sqrt($sumSqX * $sumSqY);
        // Undefined (not zero) when either series has no variance at all
        // (e.g. an identical value every day) — nothing to correlate against.
        $r = $denominator > 0.0 ? max(-1.0, min(1.0, $numerator / $denominator)) : null;

        return [
            'r' => $r !== null ? round($r, 3) : null,
            'paired_count' => $n,
            'sufficient_data' => true,
            'strength' => $this->strengthFor($r),
            'direction' => $this->directionFor($r),
        ];
    }

    /**
     * @param  array{r: ?float, paired_count: int, sufficient_data: bool, strength: string, direction: string}  $result
     */
    public function describe(array $result, string $labelA, string $labelB): string
    {
        if (! $result['sufficient_data']) {
            return "Belum cukup data untuk melihat pola antara {$labelA} dan {$labelB}.";
        }

        if ($result['strength'] === 'none') {
            return "Belum terlihat pola yang jelas antara {$labelA} dan {$labelB} pada periode ini.";
        }

        $strengthLabel = match ($result['strength']) {
            'weak' => 'lemah',
            'moderate' => 'sedang',
            'strong' => 'kuat',
            default => $result['strength'],
        };

        $directionLabel = $result['direction'] === 'positive' ? 'searah' : 'berlawanan arah';

        return "{$labelA} berkorelasi {$strengthLabel} dan {$directionLabel} dengan {$labelB} (tren teramati).";
    }

    /** Heuristic convention (not a statistical law), same "transparent, documented threshold" framing as AcuteChronicLoadCalculator::riskLevelFor(). */
    private function strengthFor(?float $r): string
    {
        if ($r === null) {
            return 'none';
        }

        $abs = abs($r);

        return match (true) {
            $abs < 0.1 => 'none',
            $abs < 0.3 => 'weak',
            $abs < 0.6 => 'moderate',
            default => 'strong',
        };
    }

    private function directionFor(?float $r): string
    {
        if ($r === null || abs($r) < 0.1) {
            return 'none';
        }

        return $r > 0 ? 'positive' : 'negative';
    }
}
