@props(['series'])

<div x-data="healthTrendChart(@js($series))">
    <div class="flex flex-wrap gap-2">
        <template x-for="key in metricKeys" :key="key">
            <button
                type="button"
                @click="activeMetric = key"
                :class="activeMetric === key
                    ? 'bg-gray-900 dark:bg-white text-white dark:text-gray-900'
                    : 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200'"
                class="px-3 py-1.5 rounded-full text-xs font-bold transition"
                x-text="series[key].label"
            ></button>
        </template>
    </div>

    <div class="mt-4 flex items-center justify-between">
        <div>
            <p class="text-3xl font-black text-gray-900 dark:text-gray-100" x-text="latestLabel"></p>
            <p class="text-xs font-semibold text-gray-400" x-text="currentSeries.label + (currentSeries.unit ? ' (' + currentSeries.unit + ')' : '')"></p>
        </div>
        <div class="flex gap-1 rounded-full bg-gray-100 dark:bg-gray-800 p-1">
            <template x-for="r in [7, 30, 90]" :key="r">
                <button
                    type="button"
                    @click="range = r"
                    :class="range === r ? 'bg-white dark:bg-gray-700 shadow-sm text-gray-900 dark:text-white' : 'text-gray-400'"
                    class="px-3 py-1 rounded-full text-xs font-bold transition"
                    x-text="r + 'D'"
                ></button>
            </template>
        </div>
    </div>

    <template x-if="currentPoints.length === 0">
        <p class="mt-8 mb-8 text-center text-sm text-gray-400">Belum ada data untuk metrik ini.</p>
    </template>

    <div class="relative mt-4" x-show="currentPoints.length > 0" x-cloak>
        <svg
            :viewBox="`0 0 ${width} ${height}`"
            class="w-full h-48 select-none"
            @mousemove="onMove($event)"
            @mouseleave="hoverIndex = null"
            @touchmove="onMove($event.touches[0])"
            @touchend="hoverIndex = null"
        >
            <line x1="0" :x2="width" :y1="padTop" :y2="padTop" stroke="currentColor" class="text-gray-100 dark:text-gray-800" stroke-width="1" />
            <line x1="0" :x2="width" :y1="height - padBottom" :y2="height - padBottom" stroke="currentColor" class="text-gray-100 dark:text-gray-800" stroke-width="1" />

            <path :d="areaPath" :fill="currentSeries.color" fill-opacity="0.1" stroke="none"></path>
            <path :d="linePath" fill="none" :stroke="currentSeries.color" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>

            <template x-if="lastPoint">
                <circle :cx="lastPoint.x" :cy="lastPoint.y" r="4" :fill="currentSeries.color" stroke="white" stroke-width="2"></circle>
            </template>

            <template x-if="hoverIndex !== null">
                <g>
                    <line :x1="hoverPoint.x" :x2="hoverPoint.x" :y1="padTop" :y2="height - padBottom" stroke="currentColor" class="text-gray-300 dark:text-gray-600" stroke-width="1"></line>
                    <circle :cx="hoverPoint.x" :cy="hoverPoint.y" r="5" :fill="currentSeries.color" stroke="white" stroke-width="2"></circle>
                </g>
            </template>
        </svg>

        <div
            x-show="hoverIndex !== null"
            x-cloak
            class="pointer-events-none absolute top-0 -translate-x-1/2 rounded-xl bg-gray-900 dark:bg-black px-3 py-1.5 text-xs font-bold text-white shadow-lg whitespace-nowrap"
            :style="`left: ${hoverPercent}%`"
        >
            <span x-text="hoverValueLabel"></span>
            <span class="ml-1 font-normal text-gray-300" x-text="hoverDateLabel"></span>
        </div>
    </div>
</div>

@once
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('healthTrendChart', (seriesData) => ({
                    series: seriesData,
                    metricKeys: Object.keys(seriesData),
                    activeMetric: Object.keys(seriesData)[0],
                    range: 7,
                    width: 600,
                    height: 190,
                    padTop: 10,
                    padBottom: 10,
                    hoverIndex: null,

                    get currentSeries() {
                        return this.series[this.activeMetric];
                    },

                    get currentPoints() {
                        const all = this.currentSeries.points;
                        return all.slice(Math.max(0, all.length - this.range));
                    },

                    get scaledPoints() {
                        const points = this.currentPoints;
                        if (points.length === 0) return [];

                        const values = points.map(p => p.value);
                        const min = Math.min(...values);
                        const max = Math.max(...values);
                        const span = (max - min) || 1;
                        const usableHeight = this.height - this.padTop - this.padBottom;
                        const stepX = points.length > 1 ? this.width / (points.length - 1) : 0;

                        return points.map((p, i) => ({
                            x: points.length > 1 ? i * stepX : this.width / 2,
                            y: this.padTop + usableHeight - ((p.value - min) / span) * usableHeight,
                            date: p.date,
                            value: p.value,
                        }));
                    },

                    get linePath() {
                        const pts = this.scaledPoints;
                        if (pts.length === 0) return '';
                        return pts.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x.toFixed(1)} ${p.y.toFixed(1)}`).join(' ');
                    },

                    get areaPath() {
                        const pts = this.scaledPoints;
                        if (pts.length === 0) return '';
                        const baseline = this.height - this.padBottom;
                        const first = pts[0];
                        const last = pts[pts.length - 1];
                        return `${this.linePath} L ${last.x.toFixed(1)} ${baseline} L ${first.x.toFixed(1)} ${baseline} Z`;
                    },

                    get lastPoint() {
                        const pts = this.scaledPoints;
                        return pts.length ? pts[pts.length - 1] : null;
                    },

                    get latestLabel() {
                        const last = this.lastPoint;
                        if (!last) return '--';
                        return this.formatValue(last.value);
                    },

                    get hoverPoint() {
                        return this.hoverIndex !== null ? this.scaledPoints[this.hoverIndex] : null;
                    },

                    get hoverPercent() {
                        return this.hoverPoint ? (this.hoverPoint.x / this.width) * 100 : 0;
                    },

                    get hoverValueLabel() {
                        return this.hoverPoint ? this.formatValue(this.hoverPoint.value) : '';
                    },

                    get hoverDateLabel() {
                        if (!this.hoverPoint) return '';
                        const d = new Date(this.hoverPoint.date + 'T00:00:00');
                        return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
                    },

                    formatValue(value) {
                        const decimals = this.activeMetric === 'sleep' ? 1 : 0;
                        return value.toLocaleString('id-ID', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
                    },

                    onMove(evt) {
                        const pts = this.scaledPoints;
                        if (pts.length === 0) return;

                        const svg = evt.currentTarget.closest('svg') || evt.target.closest('svg');
                        const rect = svg.getBoundingClientRect();
                        const relativeX = ((evt.clientX - rect.left) / rect.width) * this.width;

                        let nearest = 0;
                        let nearestDist = Infinity;
                        pts.forEach((p, i) => {
                            const dist = Math.abs(p.x - relativeX);
                            if (dist < nearestDist) {
                                nearestDist = dist;
                                nearest = i;
                            }
                        });

                        this.hoverIndex = nearest;
                    },
                }));
            });
        </script>
@endonce
