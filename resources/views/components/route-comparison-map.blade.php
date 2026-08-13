@props(['routes'])

{{-- routes: list<{label, color, points: list<{lat,lng}>}> --}}

@php
    $renderableRoutes = collect($routes)->filter(fn ($r) => count($r['points']) > 1)->values();
@endphp

@if ($renderableRoutes->isNotEmpty())
    <div>
        <div class="flex flex-wrap gap-3 mb-2">
            @foreach ($renderableRoutes as $route)
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500">
                    <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $route['color'] }}"></span>
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
            <div x-ref="map" class="w-full h-72 rounded-2xl overflow-hidden"></div>
        </div>
    </div>
@endif
