<?php

namespace Tests\Unit;

use App\Services\Analytics\CorrelationService;
use PHPUnit\Framework\TestCase;

class CorrelationServiceTest extends TestCase
{
    private CorrelationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CorrelationService;
    }

    public function test_align_inner_joins_on_matching_dates_and_drops_the_rest(): void
    {
        $seriesA = [
            ['date' => '2026-08-01', 'value' => 1.0],
            ['date' => '2026-08-02', 'value' => 2.0],
            ['date' => '2026-08-03', 'value' => 3.0],
        ];
        $seriesB = [
            ['date' => '2026-08-02', 'value' => 20.0],
            ['date' => '2026-08-03', 'value' => 30.0],
            ['date' => '2026-08-04', 'value' => 40.0],
        ];

        $result = $this->service->align($seriesA, $seriesB);

        $this->assertSame(['2026-08-02', '2026-08-03'], $result['dates']);
        $this->assertSame([2.0, 3.0], $result['x']);
        $this->assertSame([20.0, 30.0], $result['y']);
    }

    public function test_returns_insufficient_data_below_seven_paired_points(): void
    {
        $x = [1.0, 2.0, 3.0, 4.0, 5.0, 6.0];
        $y = [2.0, 4.0, 6.0, 8.0, 10.0, 12.0];

        $result = $this->service->pearson($x, $y);

        $this->assertNull($result['r']);
        $this->assertFalse($result['sufficient_data']);
        $this->assertSame('insufficient_data', $result['strength']);
        $this->assertSame(6, $result['paired_count']);
    }

    public function test_perfect_positive_correlation(): void
    {
        $x = [1.0, 2.0, 3.0, 4.0, 5.0, 6.0, 7.0];
        $y = array_map(fn ($v) => 2 * $v + 1, $x);

        $result = $this->service->pearson($x, $y);

        $this->assertSame(1.0, $result['r']);
        $this->assertSame('strong', $result['strength']);
        $this->assertSame('positive', $result['direction']);
    }

    public function test_perfect_negative_correlation(): void
    {
        $x = [1.0, 2.0, 3.0, 4.0, 5.0, 6.0, 7.0];
        $y = array_map(fn ($v) => -2 * $v + 10, $x);

        $result = $this->service->pearson($x, $y);

        $this->assertSame(-1.0, $result['r']);
        $this->assertSame('strong', $result['strength']);
        $this->assertSame('negative', $result['direction']);
    }

    public function test_moderate_strength_bucket(): void
    {
        // Hand-computed: r = 10/28 = 0.357.
        $x = [1.0, 2.0, 3.0, 4.0, 5.0, 6.0, 7.0];
        $y = [5.0, 3.0, 7.0, 2.0, 6.0, 4.0, 8.0];

        $result = $this->service->pearson($x, $y);

        $this->assertSame(0.357, $result['r']);
        $this->assertSame('moderate', $result['strength']);
        $this->assertSame('positive', $result['direction']);
    }

    public function test_weak_strength_bucket(): void
    {
        // Hand-computed: r = 6/28 = 0.214.
        $x = [1.0, 2.0, 3.0, 4.0, 5.0, 6.0, 7.0];
        $y = [1.0, 7.0, 2.0, 6.0, 3.0, 5.0, 4.0];

        $result = $this->service->pearson($x, $y);

        $this->assertSame(0.214, $result['r']);
        $this->assertSame('weak', $result['strength']);
    }

    public function test_no_relationship_bucket(): void
    {
        // Hand-computed: r = 0/28 = 0.0 exactly.
        $x = [1.0, 2.0, 3.0, 4.0, 5.0, 6.0, 7.0];
        $y = [5.0, 2.0, 6.0, 1.0, 7.0, 3.0, 4.0];

        $result = $this->service->pearson($x, $y);

        $this->assertSame(0.0, $result['r']);
        $this->assertSame('none', $result['strength']);
        $this->assertSame('none', $result['direction']);
    }

    public function test_zero_variance_series_returns_null_r_rather_than_dividing_by_zero(): void
    {
        $x = [1.0, 2.0, 3.0, 4.0, 5.0, 6.0, 7.0];
        $y = [5.0, 5.0, 5.0, 5.0, 5.0, 5.0, 5.0];

        $result = $this->service->pearson($x, $y);

        $this->assertNull($result['r']);
        $this->assertTrue($result['sufficient_data']);
        $this->assertSame('none', $result['strength']);
    }

    public function test_describe_never_uses_causal_wording(): void
    {
        $strong = $this->service->pearson(
            [1.0, 2.0, 3.0, 4.0, 5.0, 6.0, 7.0],
            [2.0, 4.0, 6.0, 8.0, 10.0, 12.0, 14.0],
        );
        $insufficient = $this->service->pearson([1.0, 2.0], [2.0, 4.0]);

        foreach ([$strong, $insufficient] as $result) {
            $description = $this->service->describe($result, 'Sleep', 'Recovery');
            $this->assertStringNotContainsStringIgnoringCase('menyebabkan', $description);
            $this->assertStringNotContainsStringIgnoringCase('karena', $description);
        }
    }

    public function test_describe_states_insufficient_data_below_the_gate(): void
    {
        $result = $this->service->pearson([1.0, 2.0], [2.0, 4.0]);

        $description = $this->service->describe($result, 'Sleep', 'Recovery');

        $this->assertStringContainsString('Belum cukup data', $description);
    }
}
