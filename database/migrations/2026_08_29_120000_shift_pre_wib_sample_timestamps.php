<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rows in these tables were written while the app ran in UTC, even though every
 * user is in Asia/Jakarta and the Garmin feed's local-time fields were stored
 * verbatim. Now that APP_TIMEZONE is Asia/Jakarta the GMT-sourced timestamps
 * (samples, per-lap start) read 7h behind local, so an activity no longer lines
 * up with its own laps and samples. Shift them once.
 *
 * No-op on a fresh database (tables empty) and runs exactly once.
 */
return new class extends Migration
{
    private const HOURS = 7;

    /** @var list<array{0: string, 1: string}> */
    private const TARGETS = [
        ['workout_samples', 'timestamp'],
        ['heart_rate_samples', 'timestamp'],
        ['workout_laps', 'start_time'],
    ];

    public function up(): void
    {
        $this->shift(self::HOURS);
    }

    public function down(): void
    {
        $this->shift(-self::HOURS);
    }

    private function shift(int $hours): void
    {
        $sqlite = DB::connection()->getDriverName() === 'sqlite';

        foreach (self::TARGETS as [$table, $column]) {
            $expr = $sqlite
                ? sprintf("datetime(%s, '%+d hours')", $column, $hours)
                : sprintf('%s + INTERVAL %d HOUR', $column, $hours);

            DB::table($table)->whereNotNull($column)->update([$column => DB::raw($expr)]);
        }
    }
};
