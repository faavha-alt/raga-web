@props(['routes'])

{{-- routes: list<{label, color, points: list<{lat,lng}>}> --}}

@php
    $renderableRoutes = collect($routes)->filter(fn ($r) => count($r['points']) > 1)->values();
@endphp

@if ($renderableRoutes->isNotEmpty())
    <div>
        <div class="mb-2 flex flex-wrap gap-3">
            @foreach ($renderableRoutes as $route)
                <span class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-[0.08em] text-telemetry-slate">
                    <span class="h-2.5 w-2.5" style="background-color: {{ $route['color'] }}"></span>
                    {{ $route['label'] }}
                </span>
            @endforeach
        </div>
        <div
            x-data="{
                routes: @js($renderableRoutes),
                init() {
                    this.$nextTick(() => {
                        const map = L.map(this.$refs.map, { scrollWheelZoom: false });

                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; <a href=&quot;https://www.openstreetmap.org/copyright&quot;>OpenStreetMap</a> contributors',
                            maxZoom: 19,
                        }).addTo(map);

                        let bounds = null;
                        this.routes.forEach(route => {
                            const latlngs = route.points.map(p => [p.lat, p.lng]);
                            const line = L.polyline(latlngs, { color: route.color, weight: 4 }).addTo(map);
                            bounds = bounds ? bounds.extend(line.getBounds()) : line.getBounds();
                        });

                        if (bounds) {
                            map.fitBounds(bounds, { padding: [24, 24] });
                        }
                    });
                },
            }"
            x-init="init()"
        >
            <div x-ref="map" class="h-72 w-full overflow-hidden rounded-lg border border-telemetry-line"></div>
        </div>
    </div>
@endif
