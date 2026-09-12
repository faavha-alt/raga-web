<?php

namespace Tests\Unit;

use App\Models\PersonalRecord;
use PHPUnit\Framework\TestCase;

/**
 * Konvensi satuan Personal Record dari Garmin:
 *  - `fastest_*` (1K/1mi/5K/10K/HM/marathon) → detik (durasi)
 *  - `longest_run` → meter (jarak), sama seperti PR jarak lain di feed yang sama
 *
 * Sebelumnya `longest_run` ikut diformat sebagai durasi sehingga PR 10,25 km
 * tampil sebagai "2:50:46" di halaman Running/Training/AI.
 */
class PersonalRecordFormattingTest extends TestCase
{
    public function test_fastest_records_are_formatted_as_durations(): void
    {
        $this->assertSame('20:34', $this->record('fastest_5k', 1234)->formattedValue());
        $this->assertSame('1:42:03', $this->record('fastest_marathon', 6123)->formattedValue());
    }

    public function test_longest_run_is_formatted_as_distance_in_km(): void
    {
        $this->assertSame('10.25 km', $this->record('longest_run', 10246.01953125)->formattedValue());
        $this->assertSame('21.50 km', $this->record('longest_run', 21500)->formattedValue());
    }

    public function test_unknown_garmin_record_keeps_raw_number(): void
    {
        $this->assertSame('1,359.0', $this->record('garmin_pr_type_9', 1359)->formattedValue());
    }

    private function record(string $type, float $value): PersonalRecord
    {
        return new PersonalRecord(['type' => $type, 'value' => $value]);
    }
}
