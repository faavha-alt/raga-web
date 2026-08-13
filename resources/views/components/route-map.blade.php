@props(['points'])

@if (count($points) > 1)
    <div
        x-data="{
            points: @js($points),
            init() {
                this.$nextTick(() => {
                    const latlngs = this.points.map(p => [p.lat, p.lng]);
                    const map = L.map(this.$refs.map, { scrollWheelZoom: false });

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href=&quot;https://www.openstreetmap.org/copyright&quot;>OpenStreetMap</a> contributors',
                        maxZoom: 19,
                    }).addTo(map);

                    const line = L.polyline(latlngs, { color: '#6C5CE7', weight: 4 }).addTo(map);
                    L.circleMarker(latlngs[0], { radius: 6, color: '#fff', weight: 2, fillColor: '#1baf7a', fillOpacity: 1 }).addTo(map);
                    L.circleMarker(latlngs[latlngs.length - 1], { radius: 6, color: '#fff', weight: 2, fillColor: '#e34948', fillOpacity: 1 }).addTo(map);

                    map.fitBounds(line.getBounds(), { padding: [24, 24] });
                });
            },
        }"
        x-init="init()"
    >
        <div x-ref="map" class="w-full h-72 rounded-2xl overflow-hidden"></div>
    </div>
@endif
