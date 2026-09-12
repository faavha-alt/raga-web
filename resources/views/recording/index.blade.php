<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 leading-tight">
            Rekam Aktivitas
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500">Rekam lari, sepeda, atau jalan langsung dari GPS ponsel — tanpa Garmin.</p>
    </x-slot>

    @php
        $recorderConfig = [
            'storeUrl' => route('recordings.store'),
            'storageKey' => 'raga.recording.v1',
            // Fail-closed: aktivitas baru selalu privat sampai pengguna
            // secara eksplisit memilih opsi lain di form.
            'defaultVisibility' => \App\Support\ActivityVisibility::Private->value,
            'sports' => [
                ['value' => 'running', 'label' => 'Lari', 'noun' => 'Lari'],
                ['value' => 'trail_running', 'label' => 'Lari Trail', 'noun' => 'Lari trail'],
                ['value' => 'cycling', 'label' => 'Bersepeda', 'noun' => 'Bersepeda'],
                ['value' => 'walking', 'label' => 'Jalan', 'noun' => 'Jalan'],
                ['value' => 'hiking', 'label' => 'Hiking', 'noun' => 'Hiking'],
            ],
            'visibilities' => collect($visibilityOptions)->map(fn ($option) => [
                'value' => $option->value,
                'label' => $option->label(),
                'description' => $option->description(),
                'icon' => $option->icon(),
            ])->values()->all(),
        ];
    @endphp

    <div class="py-6 pb-16">
        <div
            class="px-4 sm:px-6 lg:px-8 space-y-6 max-w-5xl mx-auto"
            x-data="recorder(@js($recorderConfig))"
            x-init="init()"
        >
            {{-- Banner pemulihan rekaman dari localStorage --}}
            <div x-show="hasRestorable" x-cloak class="rounded-2xl border border-raga-moderate/40 bg-raga-moderate/10 px-5 py-4">
                <p class="text-sm font-bold text-gray-900">Ada rekaman yang belum tersimpan.</p>
                <p class="mt-1 text-sm text-gray-600">
                    Rekaman sebelumnya (<span x-text="restorableSummary"></span>) ditemukan di perangkat ini.
                </p>
                <div class="mt-3 flex gap-2">
                    <x-primary-button type="button" x-on:click="restoreRecording()">Lanjutkan rekaman</x-primary-button>
                    <x-secondary-button type="button" x-on:click="discardRestored()">Buang</x-secondary-button>
                </div>
            </div>

            {{-- Error izin lokasi --}}
            <div x-show="gpsError" x-cloak class="rounded-2xl border border-raga-low/40 bg-raga-low/10 px-5 py-4">
                <p class="text-sm font-bold text-gray-900">Lokasi tidak dapat diakses</p>
                <p class="mt-1 text-sm text-gray-600" x-text="gpsError"></p>
                <div class="mt-3 flex gap-2">
                    <x-primary-button type="button" x-on:click="retryLocation()">Coba lagi</x-primary-button>
                    <x-secondary-button type="button" x-on:click="$dispatch('open-modal', 'manual-entry')">Isi manual saja</x-secondary-button>
                </div>
            </div>

            {{-- Banner sinyal GPS lemah --}}
            <div x-show="isSignalWeak" x-cloak class="rounded-2xl border border-raga-moderate/40 bg-raga-moderate/10 px-5 py-3">
                <p class="text-sm font-bold text-raga-moderate">
                    Sinyal GPS lemah (akurasi <span x-text="accuracyLabel"></span>).
                </p>
                <p class="mt-0.5 text-xs text-gray-600">Jarak dan elevasi bisa kurang akurat. Cari area terbuka bila memungkinkan.</p>
            </div>

            {{-- Kesalahan validasi server --}}
            <div x-show="serverErrors.length > 0" x-cloak class="rounded-2xl border border-raga-low/40 bg-raga-low/10 px-5 py-4">
                <p class="text-sm font-bold text-gray-900">Rekaman belum bisa disimpan</p>
                <ul class="mt-1 list-disc list-inside text-sm text-gray-600">
                    <template x-for="error in serverErrors" :key="error">
                        <li x-text="error"></li>
                    </template>
                </ul>
                <p class="mt-2 text-xs text-gray-500">Data rekaman tetap aman di perangkat ini — perbaiki lalu coba simpan lagi.</p>
                <div class="mt-3">
                    <x-primary-button type="button" x-on:click="retrySubmit()" x-bind:disabled="submitting">Simpan ulang</x-primary-button>
                </div>
            </div>

            {{-- Peta --}}
            <x-card class="!p-3">
                <div x-ref="map" class="w-full h-80 rounded-2xl overflow-hidden bg-gray-100"></div>
                <div class="mt-2 flex items-center justify-between px-2 text-xs text-gray-500">
                    <span x-text="statusLabel"></span>
                    <span>Akurasi GPS: <span x-text="accuracyLabel"></span></span>
                </div>
            </x-card>

            {{-- Readout langsung (nilai diisi Alpine, bukan server) --}}
            @php
                $tiles = [
                    ['icon' => '⏱️', 'label' => 'Waktu', 'expr' => 'formattedElapsed', 'unit' => null],
                    ['icon' => '📏', 'label' => 'Jarak', 'expr' => 'distanceKm', 'unit' => 'km'],
                    ['icon' => '⚡', 'label' => 'Pace kini', 'expr' => 'formatPace(currentPace)', 'unit' => null],
                    ['icon' => '🎯', 'label' => 'Pace rata-rata', 'expr' => 'formatPace(averagePace)', 'unit' => null],
                    ['icon' => '⛰️', 'label' => 'Elevasi naik', 'expr' => 'elevationGainLabel', 'unit' => 'm'],
                    ['icon' => '📡', 'label' => 'Akurasi', 'expr' => 'accuracyLabel', 'unit' => null],
                ];
            @endphp
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                @foreach ($tiles as $tile)
                    <div class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700/60 p-4 shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
                        <div class="flex items-center gap-2">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-50 dark:bg-gray-900 text-sm">{{ $tile['icon'] }}</span>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">{{ $tile['label'] }}</p>
                        </div>
                        <p class="mt-2.5 text-[26px] leading-none font-black text-gray-900 dark:text-gray-100">
                            <span x-text="{{ $tile['expr'] }}"></span>@if ($tile['unit'])<span class="text-sm font-semibold text-gray-400"> {{ $tile['unit'] }}</span>@endif
                        </p>
                    </div>
                @endforeach
            </div>

            {{-- Kontrol --}}
            <x-card>
                <div class="flex flex-wrap items-center gap-3">
                    <x-primary-button
                        type="button"
                        x-show="status === 'idle'"
                        x-on:click="start()"
                    >▶ Mulai merekam</x-primary-button>

                    <x-secondary-button
                        type="button"
                        x-show="status === 'recording'"
                        x-on:click="pause()"
                    >⏸ Jeda</x-secondary-button>

                    <x-primary-button
                        type="button"
                        x-show="status === 'paused'"
                        x-on:click="resume()"
                    >▶ Lanjutkan</x-primary-button>

                    <x-secondary-button
                        type="button"
                        x-show="status === 'recording' || status === 'paused' || status === 'finished'"
                        x-on:click="finish()"
                    >🏁 Selesai</x-secondary-button>

                    <x-danger-button
                        type="button"
                        x-show="status !== 'idle'"
                        x-on:click="reset()"
                    >Batalkan</x-danger-button>

                    <x-secondary-button type="button" x-on:click="$dispatch('open-modal', 'manual-entry')">
                        Input manual
                    </x-secondary-button>

                    <span x-show="submitting" class="text-sm font-medium text-gray-500">Menyimpan…</span>
                </div>
            </x-card>

            {{-- Detail aktivitas --}}
            <x-card>
                <h3 class="text-lg font-bold text-gray-900">Detail aktivitas</h3>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="sport" value="Jenis olahraga" />
                        <select
                            id="sport"
                            x-model="sport"
                            x-on:change="refreshSuggestedTitle()"
                            class="mt-1 w-full rounded-2xl border-2 border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-900 focus:border-raga-primary focus:bg-white focus:ring-raga-primary transition"
                        >
                            <template x-for="option in config.sports" :key="option.value">
                                <option :value="option.value" x-text="option.label"></option>
                            </template>
                        </select>
                    </div>

                    <div>
                        <x-input-label for="record-title" value="Judul" />
                        <x-text-input id="record-title" type="text" x-model="title" x-on:input="titleEdited = true" placeholder="mis. Lari pagi" />
                    </div>
                </div>

                <div class="mt-4">
                    <x-input-label for="record-description" value="Deskripsi (opsional)" />
                    <textarea
                        id="record-description"
                        x-model="description"
                        rows="3"
                        class="mt-1 w-full rounded-2xl border-2 border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-900 placeholder:text-gray-400 focus:border-raga-primary focus:bg-white focus:ring-raga-primary transition"
                        placeholder="Catatan tentang aktivitas ini…"
                    ></textarea>
                </div>

                <div class="mt-5">
                    <x-input-label value="Siapa yang bisa melihat?" />
                    <div class="mt-2 grid gap-3 sm:grid-cols-3">
                        @foreach ($visibilityOptions as $option)
                            <label class="flex cursor-pointer items-start gap-3 rounded-2xl border-2 border-gray-200 bg-gray-50 p-4 transition hover:border-raga-primary has-[:checked]:border-raga-primary has-[:checked]:bg-raga-primary/5">
                                <input type="radio" name="visibility" value="{{ $option->value }}" x-model="visibility" @checked($option->value === \App\Support\ActivityVisibility::Private->value) class="mt-1 text-raga-primary focus:ring-raga-primary">
                                <span>
                                    <span class="block text-sm font-bold text-gray-900">{{ $option->icon() }} {{ $option->label() }}</span>
                                    <span class="mt-0.5 block text-xs text-gray-500">{{ $option->description() }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs text-gray-500">Default aplikasi adalah <span class="font-bold">Hanya saya</span> — pilih "Semua orang" bila ingin tampil di feed seperti Strava.</p>
                </div>
            </x-card>

            {{-- Modal input manual (tanpa GPS / treadmill) --}}
            <x-modal name="manual-entry" maxWidth="md">
                <div class="p-6">
                    <h3 class="text-lg font-bold text-gray-900">Input manual</h3>
                    <p class="mt-1 text-sm text-gray-500">Untuk treadmill atau latihan tanpa sinyal GPS.</p>

                    <div class="mt-4">
                        <x-input-label for="manual-distance" value="Jarak (km)" />
                        <x-text-input id="manual-distance" type="number" step="0.01" min="0" x-model="manualDistanceKm" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="manual-duration" value="Durasi (menit)" />
                        <x-text-input id="manual-duration" type="number" step="1" min="0" x-model="manualDurationMinutes" />
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'manual-entry')">Batal</x-secondary-button>
                        <x-primary-button type="button" x-on:click="submitManual()" x-bind:disabled="submitting">Simpan aktivitas</x-primary-button>
                    </div>
                </div>
            </x-modal>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('recorder', (config) => ({
                    config,
                    storageKey: config.storageKey,
                    status: 'idle', // idle | recording | paused | finished
                    points: [],
                    segments: [],
                    distanceMeters: 0,
                    maxSpeedMps: 0,
                    movingSeconds: 0,
                    elevationGainMeters: 0,
                    currentPace: null,
                    accuracyMeters: null,
                    gpsError: null,
                    startedAtMs: null,
                    pausedAccumMs: 0,
                    pauseStartedMs: null,
                    tickTimer: null,
                    watchId: null,
                    wakeLock: null,
                    wakeLockSupported: 'wakeLock' in navigator,
                    map: null,
                    polyline: null,
                    marker: null,
                    sport: config.sports[0].value,
                    title: '',
                    titleEdited: false,
                    description: '',
                    visibility: config.defaultVisibility,
                    submitting: false,
                    serverErrors: [],
                    manualDistanceKm: '',
                    manualDurationMinutes: '',
                    hasRestorable: false,
                    restorable: null,

                    // Batas laju wajar, disamakan dengan TrackMetricsCalculator di server.
                    maxPlausibleSpeed() {
                        return this.sport === 'cycling' ? 30 : (this.sport === 'walking' || this.sport === 'hiking' ? 6 : 12);
                    },

                    init() {
                        this.initMap();
                        this.checkRestorable();
                        this.refreshSuggestedTitle();
                        // Timer UI 1 detik; hanya berjalan saat merekam.
                        this.tickTimer = setInterval(() => this.tick(), 1000);
                        document.addEventListener('visibilitychange', () => {
                            if (document.visibilityState === 'visible' && this.status === 'recording') {
                                this.requestWakeLock();
                            }
                        });
                    },

                    initMap() {
                        if (!window.L || !this.$refs.map) {
                            return;
                        }
                        this.map = L.map(this.$refs.map, { scrollWheelZoom: false }).setView([-6.2, 106.8], 15);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                            maxZoom: 19,
                        }).addTo(this.map);
                        this.polyline = L.polyline([], { color: '#6C5CE7', weight: 4 }).addTo(this.map);
                        this.marker = null;
                    },

                    get elapsedSeconds() {
                        if (this.startedAtMs === null) {
                            return 0;
                        }
                        const base = Date.now() - this.startedAtMs - this.pausedAccumMs;
                        const paused = this.pauseStartedMs !== null ? Date.now() - this.pauseStartedMs : 0;
                        return Math.max(0, Math.floor((base - paused) / 1000));
                    },

                    get formattedElapsed() {
                        const total = this.elapsedSeconds;
                        const hours = String(Math.floor(total / 3600)).padStart(2, '0');
                        const minutes = String(Math.floor((total % 3600) / 60)).padStart(2, '0');
                        const seconds = String(total % 60).padStart(2, '0');
                        return `${hours}:${minutes}:${seconds}`;
                    },

                    get distanceKm() {
                        return (this.distanceMeters / 1000).toFixed(2);
                    },

                    get elevationGainLabel() {
                        return Math.round(this.elevationGainMeters);
                    },

                    get accuracyLabel() {
                        return this.accuracyMeters === null ? '--' : `${Math.round(this.accuracyMeters)} m`;
                    },

                    get isSignalWeak() {
                        return this.status === 'recording' && this.accuracyMeters !== null && this.accuracyMeters > 30;
                    },

                    get averagePace() {
                        if (this.distanceMeters <= 0 || this.movingSeconds <= 0) {
                            return null;
                        }
                        return this.movingSeconds / (this.distanceMeters / 1000);
                    },

                    get statusLabel() {
                        return {
                            idle: 'Siap merekam',
                            recording: 'Merekam…',
                            paused: 'Dijeda',
                            finished: 'Selesai',
                        }[this.status] || '';
                    },

                    get restorableSummary() {
                        if (!this.restorable) {
                            return '';
                        }
                        const km = (this.restorable.distanceMeters / 1000).toFixed(2);
                        return `${km} km, ${this.restorable.points.length} titik`;
                    },

                    formatPace(seconds) {
                        if (!seconds || !isFinite(seconds)) {
                            return '--';
                        }
                        const minutes = Math.floor(seconds / 60);
                        const rest = String(Math.round(seconds % 60)).padStart(2, '0');
                        return `${minutes}:${rest} /km`;
                    },

                    suggestedTitle() {
                        const sport = this.config.sports.find((item) => item.value === this.sport);
                        const noun = sport ? sport.noun : 'Aktivitas';
                        const hour = new Date().getHours();
                        if (hour >= 4 && hour < 11) {
                            return `${noun} pagi`;
                        }
                        if (hour >= 11 && hour < 15) {
                            return `${noun} siang`;
                        }
                        if (hour >= 15 && hour < 19) {
                            return `${noun} sore`;
                        }
                        return `${noun} malam`;
                    },

                    refreshSuggestedTitle() {
                        if (!this.titleEdited) {
                            this.title = this.suggestedTitle();
                        }
                    },

                    start() {
                        this.gpsError = null;
                        if (!('geolocation' in navigator)) {
                            this.gpsError = 'Perangkat ini tidak mendukung GPS (Geolocation API). Anda masih bisa mengisi jarak dan durasi secara manual.';
                            return;
                        }

                        this.status = 'recording';
                        this.startedAtMs = Date.now();
                        this.pausedAccumMs = 0;
                        this.pauseStartedMs = null;
                        this.requestWakeLock();
                        this.beginWatch();
                    },

                    beginWatch() {
                        this.watchId = navigator.geolocation.watchPosition(
                            (position) => this.onPosition(position),
                            (error) => this.onGeoError(error),
                            { enableHighAccuracy: true, maximumAge: 0, timeout: 15000 },
                        );
                    },

                    stopWatch() {
                        if (this.watchId !== null) {
                            navigator.geolocation.clearWatch(this.watchId);
                            this.watchId = null;
                        }
                    },

                    onPosition(position) {
                        this.gpsError = null;
                        this.accuracyMeters = position.coords.accuracy;

                        if (this.status !== 'recording') {
                            return;
                        }

                        const point = {
                            t: position.timestamp || Date.now(),
                            lat: position.coords.latitude,
                            lng: position.coords.longitude,
                            alt: position.coords.altitude,
                            hr: null,
                            cadence: null,
                        };

                        this.appendPoint(point);
                    },

                    onGeoError(error) {
                        if (error.code === error.PERMISSION_DENIED) {
                            this.status = 'idle';
                            this.releaseWakeLock();
                            this.gpsError = 'Izin lokasi ditolak. Aktifkan izin lokasi di pengaturan browser, lalu tekan "Coba lagi".';
                        } else if (error.code === error.POSITION_UNAVAILABLE) {
                            this.gpsError = 'Posisi tidak tersedia. Pastikan GPS aktif dan Anda berada di area terbuka.';
                        } else {
                            this.gpsError = 'Pencarian lokasi melebihi batas waktu. Coba lagi sebentar.';
                        }
                    },

                    appendPoint(point) {
                        const previousIndex = this.points.length > 0 ? this.points.length - 1 : null;

                        if (previousIndex === null) {
                            this.points.push(point);
                            this.segments.push(null);
                        } else {
                            const previous = this.points[previousIndex];
                            const deltaSeconds = (point.t - previous.t) / 1000;
                            const meters = this.haversine(previous.lat, previous.lng, point.lat, point.lng);
                            const speed = deltaSeconds > 0 ? meters / deltaSeconds : Infinity;

                            if (deltaSeconds <= 0 || speed > this.maxPlausibleSpeed()) {
                                // Lompatan GPS: jangan masukkan titik ini ke trek/jarak.
                                this.persist();
                                return;
                            }

                            this.points.push(point);
                            this.segments.push({ meters, seconds: deltaSeconds });
                            this.distanceMeters += meters;
                            this.maxSpeedMps = Math.max(this.maxSpeedMps, speed);
                            if (deltaSeconds <= 60) {
                                this.movingSeconds += deltaSeconds;
                            }
                            this.currentPace = meters > 0 ? deltaSeconds / (meters / 1000) : null;
                        }

                        this.updateElevation();
                        this.drawTrack();
                        this.persist();
                    },

                    haversine(lat1, lng1, lat2, lng2) {
                        const radius = 6371000;
                        const toRad = (value) => (value * Math.PI) / 180;
                        const dLat = toRad(lat2 - lat1);
                        const dLng = toRad(lng2 - lng1);
                        const a = Math.sin(dLat / 2) ** 2
                            + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
                        return 2 * radius * Math.asin(Math.min(1, Math.sqrt(a)));
                    },

                    // Sama dengan server: moving average window 5 + ambang 3 m.
                    updateElevation() {
                        const altitudes = this.points.map((point) => (point.alt === null || point.alt === undefined ? null : point.alt));
                        const half = 2;
                        let gain = 0;
                        let last = null;

                        for (let i = 0; i < altitudes.length; i++) {
                            if (altitudes[i] === null) {
                                continue;
                            }
                            let sum = 0;
                            let samples = 0;
                            for (let j = Math.max(0, i - half); j <= Math.min(altitudes.length - 1, i + half); j++) {
                                if (altitudes[j] !== null) {
                                    sum += altitudes[j];
                                    samples++;
                                }
                            }
                            const smoothed = sum / samples;
                            if (last === null) {
                                last = smoothed;
                                continue;
                            }
                            const delta = smoothed - last;
                            if (Math.abs(delta) < 3) {
                                continue;
                            }
                            if (delta > 0) {
                                gain += delta;
                            }
                            last = smoothed;
                        }

                        this.elevationGainMeters = gain;
                    },

                    drawTrack() {
                        if (!this.map || !this.polyline) {
                            return;
                        }
                        const latlngs = this.points.map((point) => [point.lat, point.lng]);
                        this.polyline.setLatLngs(latlngs);

                        if (latlngs.length === 0) {
                            if (this.marker) {
                                this.map.removeLayer(this.marker);
                                this.marker = null;
                            }
                            return;
                        }

                        const last = latlngs[latlngs.length - 1];
                        if (!this.marker) {
                            this.marker = L.circleMarker(last, { radius: 8, color: '#fff', weight: 3, fillColor: '#FF6B57', fillOpacity: 1 }).addTo(this.map);
                        } else {
                            this.marker.setLatLng(last);
                        }

                        if (latlngs.length > 1) {
                            this.map.fitBounds(this.polyline.getBounds(), { padding: [24, 24], maxZoom: 17 });
                        } else {
                            this.map.setView(last, 16);
                        }
                    },

                    tick() {
                        if (this.status === 'recording') {
                            this.persist();
                        }
                    },

                    pause() {
                        if (this.status !== 'recording') {
                            return;
                        }
                        this.status = 'paused';
                        this.pauseStartedMs = Date.now();
                        this.stopWatch();
                        this.releaseWakeLock();
                        this.persist();
                    },

                    resume() {
                        if (this.status !== 'paused') {
                            return;
                        }
                        if (this.pauseStartedMs !== null) {
                            this.pausedAccumMs += Date.now() - this.pauseStartedMs;
                            this.pauseStartedMs = null;
                        }
                        this.status = 'recording';
                        this.requestWakeLock();
                        this.beginWatch();
                    },

                    finish() {
                        this.stopWatch();
                        this.releaseWakeLock();
                        this.status = 'finished';

                        if (this.points.length < 2) {
                            this.$dispatch('open-modal', 'manual-entry');
                            return;
                        }

                        this.submit(this.buildPayload());
                    },

                    buildPayload() {
                        return {
                            type: this.sport,
                            name: this.title || this.suggestedTitle(),
                            description: this.description || null,
                            visibility: this.visibility,
                            started_at: new Date(this.startedAtMs).toISOString(),
                            elapsed_seconds: this.elapsedSeconds,
                            points: this.points.map((point) => ({
                                t: point.t,
                                lat: point.lat,
                                lng: point.lng,
                                alt: point.alt,
                                hr: point.hr,
                                cadence: point.cadence,
                            })),
                        };
                    },

                    submitManual() {
                        const distanceKm = parseFloat(this.manualDistanceKm);
                        const durationMinutes = parseFloat(this.manualDurationMinutes);

                        if (!(distanceKm > 0) || !(durationMinutes > 0)) {
                            this.serverErrors = ['Jarak dan durasi manual harus diisi lebih dari nol.'];
                            return;
                        }

                        this.$dispatch('close-modal', 'manual-entry');
                        this.submit({                            type: this.sport,
                            name: this.title || this.suggestedTitle(),
                            description: this.description || null,
                            visibility: this.visibility,
                            started_at: new Date(this.startedAtMs || Date.now()).toISOString(),
                            elapsed_seconds: Math.round(durationMinutes * 60),
                            manual_distance_meters: distanceKm * 1000,
                            manual_duration_seconds: Math.round(durationMinutes * 60),
                            points: [],
                        });
                    },

                    retrySubmit() {
                        if (this.points.length >= 2) {
                            this.submit(this.buildPayload());
                        }
                    },

                    async submit(payload) {
                        this.submitting = true;
                        this.serverErrors = [];

                        try {
                            const csrf = document.querySelector('meta[name="csrf-token"]');
                            const response = await fetch(this.config.storeUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : '',
                                },
                                body: JSON.stringify(payload),
                            });
                            const body = await response.json().catch(() => ({}));

                            if (response.ok && body.redirect) {
                                window.localStorage.removeItem(this.storageKey);
                                window.location.href = body.redirect;
                                return;
                            }

                            if (response.status === 422 && body.errors) {
                                this.serverErrors = Object.values(body.errors).flat();
                            } else {
                                this.serverErrors = [body.message || 'Gagal menyimpan aktivitas. Coba lagi.'];
                            }
                        } catch (error) {
                            this.serverErrors = ['Tidak dapat menghubungi server. Rekaman tetap tersimpan di perangkat ini.'];
                        } finally {
                            this.submitting = false;
                        }
                    },

                    retryLocation() {
                        this.gpsError = null;
                        this.start();
                    },

                    reset() {
                        this.stopWatch();
                        this.releaseWakeLock();
                        this.status = 'idle';
                        this.points = [];
                        this.segments = [];
                        this.distanceMeters = 0;
                        this.movingSeconds = 0;
                        this.elevationGainMeters = 0;
                        this.maxSpeedMps = 0;
                        this.currentPace = null;
                        this.accuracyMeters = null;
                        this.gpsError = null;
                        this.startedAtMs = null;
                        this.pausedAccumMs = 0;
                        this.pauseStartedMs = null;
                        this.serverErrors = [];
                        this.drawTrack();
                        window.localStorage.removeItem(this.storageKey);
                    },

                    // ---- Wake Lock ----
                    async requestWakeLock() {
                        if (!this.wakeLockSupported || this.wakeLock) {
                            return;
                        }
                        try {
                            this.wakeLock = await navigator.wakeLock.request('screen');
                            this.wakeLock.addEventListener('release', () => {
                                this.wakeLock = null;
                            });
                        } catch (error) {
                            // Degradasi senyap: tidak semua browser mengizinkan wake lock.
                            this.wakeLock = null;
                        }
                    },

                    releaseWakeLock() {
                        if (this.wakeLock) {
                            this.wakeLock.release().catch(() => {});
                            this.wakeLock = null;
                        }
                    },

                    // ---- localStorage ----
                    persist() {
                        try {
                            window.localStorage.setItem(this.storageKey, JSON.stringify({
                                status: this.status,
                                points: this.points,
                                distanceMeters: this.distanceMeters,
                                movingSeconds: this.movingSeconds,
                                elevationGainMeters: this.elevationGainMeters,
                                startedAtMs: this.startedAtMs,
                                pausedAccumMs: this.pausedAccumMs,
                                sport: this.sport,
                                title: this.title,
                                description: this.description,
                                visibility: this.visibility,
                            }));
                        } catch (error) {
                            // Kuota localStorage penuh — abaikan, perekaman tetap jalan.
                        }
                    },

                    checkRestorable() {
                        try {
                            const raw = window.localStorage.getItem(this.storageKey);
                            if (!raw) {
                                return;
                            }
                            const data = JSON.parse(raw);
                            if (data && Array.isArray(data.points) && data.points.length > 1) {
                                this.restorable = data;
                                this.hasRestorable = true;
                            }
                        } catch (error) {
                            this.restorable = null;
                        }
                    },

                    restoreRecording() {
                        if (!this.restorable) {
                            return;
                        }
                        const data = this.restorable;
                        this.points = data.points;
                        this.segments = [];
                        this.distanceMeters = data.distanceMeters || 0;
                        this.movingSeconds = data.movingSeconds || 0;
                        this.elevationGainMeters = data.elevationGainMeters || 0;
                        this.startedAtMs = data.startedAtMs || Date.now();
                        this.pausedAccumMs = data.pausedAccumMs || 0;
                        this.sport = data.sport || this.sport;
                        this.title = data.title || '';
                        this.titleEdited = Boolean(data.title);
                        this.description = data.description || '';
                        this.visibility = data.visibility || this.config.defaultVisibility;
                        this.status = 'paused';
                        this.hasRestorable = false;
                        this.restorable = null;
                        this.updateElevation();
                        this.drawTrack();
                    },

                    discardRestored() {
                        this.hasRestorable = false;
                        this.restorable = null;
                        window.localStorage.removeItem(this.storageKey);
                    },
                }));
            });
        </script>
    @endpush
</x-app-layout>
