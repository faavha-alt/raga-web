@props(['label', 'unit' => null, 'color', 'points', 'decimals' => 0])

<x-chart-math />

<div x-data="sampleChart(@js($points), @js($decimals))">
    <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">{{ $label }}@if($unit) ({{ $unit }})@endif</p>

    <template x-if="points.length === 0">
        <p class="py-8 text-center text-sm text-gray-400">Belum ada data {{ strtolower($label) }} untuk aktivitas ini.</p>
    </template>

    <div class="relative" x-show="points.length > 0" x-cloak>
        <svg
            :viewBox="`0 0 ${width} ${height}`"
            class="w-full h-40 select-none"
            @mousemove="onMove($event)"
            @mouseleave="hoverIndex = null"
            @touchmove="onMove($event.touches[0])"
            @touchend="hoverIndex = null"
        >
            <line x1="0" :x2="width" :y1="height - padBottom" :y2="height - padBottom" stroke="currentColor" class="text-gray-100" stroke-width="1" />

            <path :d="areaPath" fill="{{ $color }}" fill-opacity="0.1" stroke="none"></path>
            <path :d="linePath" fill="none" stroke="{{ $color }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>

            <template x-if="hoverIndex !== null">
                <g>
                    <line :x1="hoverPoint.x" :x2="hoverPoint.x" :y1="padTop" :y2="height - padBottom" stroke="currentColor" class="text-gray-300" stroke-width="1"></line>
                    <circle :cx="hoverPoint.x" :cy="hoverPoint.y" r="5" fill="{{ $color }}" stroke="white" stroke-width="2"></circle>
                </g>
            </template>
        </svg>

        <div
            x-show="hoverIndex !== null"
            x-cloak
            class="pointer-events-none absolute top-0 -translate-x-1/2 rounded-xl bg-gray-900 px-3 py-1.5 text-xs font-bold text-white shadow-lg whitespace-nowrap"
            :style="`left: ${hoverPercent}%`"
        >
            <span x-text="hoverValueLabel"></span>
            <span class="ml-1 font-normal text-gray-300" x-text="hoverPoint ? hoverPoint.label : ''"></span>
        </div>
    </div>
</div>

@once
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('sampleChart', (points, decimals) => ({
                points,
                decimals,
                width: 600,
                height: 160,
                padTop: 8,
                padBottom: 8,
                hoverIndex: null,

                get scaledPoints() {
                    return window.RagaChartMath.scale(this.points, this.width, this.height, this.padTop, this.padBottom);
                },

                get linePath() {
                    return window.RagaChartMath.linePath(this.scaledPoints);
                },

                get areaPath() {
                    return window.RagaChartMath.areaPath(this.scaledPoints, this.width, this.height, this.padBottom);
                },

                get hoverPoint() {
                    return this.hoverIndex !== null ? this.scaledPoints[this.hoverIndex] : null;
                },

                get hoverPercent() {
                    return this.hoverPoint ? (this.hoverPoint.x / this.width) * 100 : 0;
                },

                get hoverValueLabel() {
                    if (!this.hoverPoint) return '';
                    return this.hoverPoint.value.toLocaleString('id-ID', { minimumFractionDigits: this.decimals, maximumFractionDigits: this.decimals });
                },

                onMove(evt) {
                    const pts = this.scaledPoints;
                    if (pts.length === 0) return;
                    const relativeX = window.RagaChartMath.relativeX(evt, this.width);
                    this.hoverIndex = window.RagaChartMath.nearestIndex(pts, relativeX);
                },
            }));
        });
    </script>
@endonce
