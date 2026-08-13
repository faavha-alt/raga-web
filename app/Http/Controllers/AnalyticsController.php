<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Analytics\AnalyticsSeriesGateway;
use App\Services\Analytics\CorrelationService;
use App\Services\Analytics\RelationshipCatalog;
use App\Services\Health\MetricSeriesService;
use App\Services\Health\PersonalBaselineService;
use App\Services\Training\TrainingVolumeSeriesService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AnalyticsController extends Controller
{
    private const HEALTH_METRICS = ['resting_hr', 'hrv', 'stress', 'sleep', 'body_battery_net', 'spo2'];

    private const TRAINING_VOLUME_METRICS = ['distance', 'duration', 'elevation_gain', 'activity_count'];

    private const DEFAULT_RELATIONSHIP_DAYS = 90;

    public function __construct(
        private MetricSeriesService $metricSeries,
        private PersonalBaselineService $baseline,
        private TrainingVolumeSeriesService $volumeSeries,
        private AnalyticsSeriesGateway $analyticsSeries,
        private CorrelationService $correlation,
        private RelationshipCatalog $catalog,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $relationships = collect($this->catalog->all())->map(function ($definition) use ($user) {
            $result = $this->correlationFor($user, $definition, self::DEFAULT_RELATIONSHIP_DAYS);

            return $definition + [
                'description' => $this->correlation->describe(
                    $result,
                    $this->analyticsSeries->meta($definition['key_a'])['label'],
                    $this->analyticsSeries->meta($definition['key_b'])['label'],
                ),
                'sufficient_data' => $result['sufficient_data'],
            ];
        })->all();

        return view('analytics.index', compact('relationships'));
    }

    public function healthTrends(Request $request): View
    {
        $user = $request->user();

        $series = [];
        $baselines = [];

        foreach (self::HEALTH_METRICS as $metric) {
            $series[$metric] = $this->metricSeries->meta($metric) + ['points' => $this->metricSeries->seriesFor($user, $metric, 365)];
            $baselines[$metric] = $this->baseline->compute($user, $metric);
        }

        return view('analytics.health-trends', compact('series', 'baselines'));
    }

    public function trainingTrends(Request $request): View
    {
        $user = $request->user();

        $series = [];
        foreach (self::TRAINING_VOLUME_METRICS as $metric) {
            $series[$metric] = $this->volumeSeries->meta($metric) + ['points' => $this->volumeSeries->seriesFor($user, $metric, 365)];
        }
        $series['training_load'] = $this->metricSeries->meta('training_load') + ['points' => $this->metricSeries->seriesFor($user, 'training_load', 365)];

        return view('analytics.training-trends', compact('series'));
    }

    public function relationship(Request $request, string $pair): View
    {
        $definition = $this->catalog->find($pair);

        if ($definition === null) {
            throw new NotFoundHttpException;
        }

        $user = $request->user();
        $days = max(7, (int) $request->query('days', self::DEFAULT_RELATIONSHIP_DAYS));

        $result = $this->correlationFor($user, $definition, $days);

        $metaA = $this->analyticsSeries->meta($definition['key_a']);
        $metaB = $this->analyticsSeries->meta($definition['key_b']);

        $series = [
            $definition['key_a'] => $metaA + ['points' => $this->analyticsSeries->seriesFor($user, $definition['key_a'], $days)],
            $definition['key_b'] => $metaB + ['points' => $this->analyticsSeries->seriesFor($user, $definition['key_b'], $days)],
        ];

        return view('analytics.relationship', [
            'definition' => $definition,
            'result' => $result,
            'description' => $this->correlation->describe($result, $metaA['label'], $metaB['label']),
            'series' => $series,
            'days' => $days,
            'disclaimer' => CorrelationService::DISCLAIMER,
        ]);
    }

    /** @return array{r: ?float, paired_count: int, sufficient_data: bool, strength: string, direction: string} */
    private function correlationFor(User $user, array $definition, int $days): array
    {
        $seriesA = $this->analyticsSeries->seriesFor($user, $definition['key_a'], $days);
        $seriesB = $this->analyticsSeries->seriesFor($user, $definition['key_b'], $days);

        $aligned = $this->correlation->align($seriesA, $seriesB);

        return $this->correlation->pearson($aligned['x'], $aligned['y']);
    }
}
