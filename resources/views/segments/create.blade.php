<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white leading-tight">
            {{ __('Buat Segment') }}
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500">Pilih satu aktivitas GPS milikmu, lalu klik titik awal dan titik akhir di peta.</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 max-w-4xl space-y-6">

            <x-card>
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-4">1️⃣ Pilih Aktivitas</p>
                @if ($workouts->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Kamu belum punya aktivitas dengan data GPS. Sinkronkan aktivitas Garmin yang punya rute terlebih dahulu.
                    </p>
                @else
                    <form method="GET" action="{{ route('segments.create') }}" class="flex flex-wrap items-end gap-3">
                        <div class="flex-1 min-w-[220px]">
                            <x-input-label for="workout_id" value="Aktivitas" />
                            <select id="workout_id" name="workout_id" required
                                class="w-full rounded-2xl border-2 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100 focus:border-raga-primary focus:ring-raga-primary transition">
                                <option value="">— Pilih aktivitas —</option>
                                @foreach ($workouts as $workout)
                                    <option value="{{ $workout->id }}" @selected($selectedWorkout && $selectedWorkout->id === $workout->id)>
                                        {{ $workout->start_date->translatedFormat('d M Y') }} · {{ $workout->name ?? \App\Support\ActivityTypeIcon::label($workout->type) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <x-secondary-button type="submit">Tampilkan Rute</x-secondary-button>
                    </form>
                @endif
            </x-card>

            @if ($selectedWorkout && count($points) > 1)
                <x-card>
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-400">2️⃣ Tentukan Titik Awal & Akhir</p>
                        <p class="text-xs text-gray-400">{{ count($points) }} titik GPS</p>
                    </div>

                    <div
                        x-data="{
                            points: @js($points),
                            startIndex: null,
                            endIndex: null,
                            startMarker: null,
                            endMarker: null,
                            map: null,
                            init() {
                                this.$nextTick(() => {
                                    const latlngs = this.points.map(p => [p.lat, p.lng]);
                                    const map = L.map(this.$refs.map, { scrollWheelZoom: false });

                                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                        attribution: '&copy; <a href=&quot;https://www.openstreetmap.org/copyright&quot;>OpenStreetMap</a> contributors',
                                        maxZoom: 19,
                                    }).addTo(map);

                                    const line = L.polyline(latlngs, { color: '#6C5CE7', weight: 4 }).addTo(map);
                                    map.fitBounds(line.getBounds(), { padding: [24, 24] });
                                    this.map = map;

                                    map.on('click', (event) => this.pick(event.latlng));
                                });
                            },
                            nearestIndex(latlng) {
                                let best = 0;
                                let bestDistance = Infinity;
                                this.points.forEach((point, index) => {
                                    const distance = this.map.distance(latlng, L.latLng(point.lat, point.lng));
                                    if (distance < bestDistance) {
                                        bestDistance = distance;
                                        best = index;
                                    }
                                });
                                return best;
                            },
                            pick(latlng) {
                                const index = this.nearestIndex(latlng);
                                if (this.startIndex === null || this.endIndex !== null) {
                                    this.startIndex = index;
                                    this.endIndex = null;
                                } else {
                                    this.endIndex = index;
                                }
                                this.drawMarkers();
                            },
                            drawMarkers() {
                                if (this.startMarker) { this.map.removeLayer(this.startMarker); this.startMarker = null; }
                                if (this.endMarker) { this.map.removeLayer(this.endMarker); this.endMarker = null; }

                                if (this.startIndex !== null) {
                                    const p = this.points[this.startIndex];
                                    this.startMarker = L.circleMarker([p.lat, p.lng], { radius: 7, color: '#fff', weight: 2, fillColor: '#1baf7a', fillOpacity: 1 }).addTo(this.map);
                                }
                                if (this.endIndex !== null) {
                                    const p = this.points[this.endIndex];
                                    this.endMarker = L.circleMarker([p.lat, p.lng], { radius: 7, color: '#fff', weight: 2, fillColor: '#e34948', fillOpacity: 1 }).addTo(this.map);
                                }
                            },
                            reset() {
                                this.startIndex = null;
                                this.endIndex = null;
                                this.drawMarkers();
                            },
                        }"
                        x-init="init()"
                    >
                        <div x-ref="map" class="w-full h-96 rounded-2xl overflow-hidden"></div>

                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <p class="text-sm font-semibold text-gray-600 dark:text-gray-300">
                                Awal: <span class="font-black text-raga-accent" x-text="startIndex ?? '—'"></span>
                                · Akhir: <span class="font-black text-raga-energy" x-text="endIndex ?? '—'"></span>
                            </p>
                            <button type="button" @click="reset()" class="text-xs font-bold text-gray-400 hover:text-gray-600 transition">Ulangi pilihan</button>
                        </div>

                        <x-input-error :messages="$errors->get('end_index')" class="mt-2" />
                        <x-input-error :messages="$errors->get('start_index')" class="mt-2" />
                        <x-input-error :messages="$errors->get('workout_id')" class="mt-2" />

                        <form method="POST" action="{{ route('segments.store') }}" class="mt-5 space-y-4">
                            @csrf
                            <input type="hidden" name="workout_id" value="{{ $selectedWorkout->id }}">
                            <input type="hidden" name="start_index" :value="startIndex ?? ''">
                            <input type="hidden" name="end_index" :value="endIndex ?? ''">

                            <div>
                                <x-input-label for="name" value="Nama Segment" />
                                <x-text-input id="name" name="name" type="text" :value="old('name')" placeholder="mis. Tanjakan Bukit Cinta" required />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="description" value="Deskripsi (opsional)" />
                                <x-text-input id="description" name="description" type="text" :value="old('description')" placeholder="Catatan singkat tentang segment ini" />
                                <x-input-error :messages="$errors->get('description')" class="mt-2" />
                            </div>

                            <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-600 dark:text-gray-300">
                                <input type="checkbox" name="is_public" value="1" checked class="rounded border-gray-300 text-raga-primary focus:ring-raga-primary">
                                Tampilkan ke semua orang
                            </label>

                            <div>
                                <x-primary-button x-bind:disabled="startIndex === null || endIndex === null || endIndex <= startIndex">
                                    Simpan Segment
                                </x-primary-button>
                                <p class="mt-2 text-xs text-gray-400">Titik akhir harus berada setelah titik awal di sepanjang lintasan.</p>
                            </div>
                        </form>
                    </div>
                </x-card>
            @endif

            <a href="{{ route('segments.index') }}" class="inline-block text-xs font-bold text-raga-primary hover:text-raga-accent transition">← Kembali ke daftar segment</a>
        </div>
    </div>
</x-app-layout>
