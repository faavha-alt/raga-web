<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Penjaga reserved word MySQL pada alias SQL mentah.
 *
 * Test suite berjalan di SQLite, yang menerima alias seperti `SUM(x) as load`
 * tanpa keluhan — di MySQL alias itu menyebabkan syntax error 1064 dan halaman
 * jadi 500 di produksi. Test ini memindai pemakaian query mentah di `app/` dan
 * menolak alias yang merupakan reserved word MySQL.
 */
class SqlAliasReservedWordTest extends TestCase
{
    private const RESERVED = [
        'load', 'read', 'key', 'rank', 'range', 'rows', 'order', 'group', 'groups',
        'desc', 'usage', 'interval', 'system', 'condition', 'match', 'lag', 'lead',
        'partition', 'signal', 'row', 'table', 'index', 'primary', 'values',
    ];

    public function test_raw_sql_aliases_do_not_use_mysql_reserved_words(): void
    {
        $offenders = [];

        foreach (File::allFiles(app_path()) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $lines = preg_split('/\R/', $file->getContents()) ?: [];

            foreach ($lines as $index => $line) {
                if (! preg_match('/\bas\s+([a-zA-Z_][a-zA-Z0-9_]*)/i', $line, $match)) {
                    continue;
                }

                $alias = strtolower($match[1]);

                if (! in_array($alias, self::RESERVED, true)) {
                    continue;
                }

                // Hanya relevan bila baris ini bagian dari query mentah — cek
                // beberapa baris sebelumnya karena selectRaw sering multi-baris.
                $context = implode(' ', array_slice($lines, max(0, $index - 3), 4));

                if (! str_contains($context, 'selectRaw')
                    && ! str_contains($context, 'DB::raw')
                    && ! str_contains($context, 'orderByRaw')
                    && ! str_contains($context, 'havingRaw')) {
                    continue;
                }

                $offenders[] = $file->getRelativePathname().':'.($index + 1).' → as '.$alias;
            }
        }

        $this->assertSame([], $offenders, 'Alias SQL bentrok reserved word MySQL: '.implode(', ', $offenders));
    }
}
