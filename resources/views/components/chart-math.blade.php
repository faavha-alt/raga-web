{{-- Shared coordinate-scaling/path-building math for the Alpine SVG line charts
     (health-trend-chart on the dashboard, sample-chart on activity detail).
     Included once per page via @once so multiple charts don't redefine it. --}}
@once
    <script>
        window.RagaChartMath = {
            scale(points, width, height, padTop, padBottom) {
                if (points.length === 0) return [];

                const values = points.map(p => p.value);
                const min = Math.min(...values);
                const max = Math.max(...values);
                const span = (max - min) || 1;
                const usableHeight = height - padTop - padBottom;
                const stepX = points.length > 1 ? width / (points.length - 1) : 0;

                return points.map((p, i) => ({
                    ...p,
                    x: points.length > 1 ? i * stepX : width / 2,
                    y: padTop + usableHeight - ((p.value - min) / span) * usableHeight,
                }));
            },

            linePath(scaledPoints) {
                if (scaledPoints.length === 0) return '';
                return scaledPoints.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x.toFixed(1)} ${p.y.toFixed(1)}`).join(' ');
            },

            areaPath(scaledPoints, width, height, padBottom) {
                if (scaledPoints.length === 0) return '';
                const baseline = height - padBottom;
                const first = scaledPoints[0];
                const last = scaledPoints[scaledPoints.length - 1];
                return `${this.linePath(scaledPoints)} L ${last.x.toFixed(1)} ${baseline} L ${first.x.toFixed(1)} ${baseline} Z`;
            },

            nearestIndex(scaledPoints, relativeX) {
                let nearest = 0;
                let nearestDist = Infinity;
                scaledPoints.forEach((p, i) => {
                    const dist = Math.abs(p.x - relativeX);
                    if (dist < nearestDist) {
                        nearestDist = dist;
                        nearest = i;
                    }
                });
                return nearest;
            },

            relativeX(evt, width) {
                const svg = evt.currentTarget.closest('svg') || evt.target.closest('svg');
                const rect = svg.getBoundingClientRect();
                return ((evt.clientX - rect.left) / rect.width) * width;
            },
        };
    </script>
@endonce
