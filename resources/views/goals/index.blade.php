<x-app-layout>
    <x-slot name="header">
        <h1 class="telemetry-value text-4xl sm:text-5xl leading-tight">
            {{ __('Goals & Progress') }}
        </h1>
        <p class="mt-2 text-sm font-medium text-telemetry-slate">Target latihan kamu dan seberapa jauh kamu sudah melangkah.</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 max-w-3xl space-y-6" x-data="{ type: '{{ old('type', 'weekly_distance') }}' }">

            @if (session('status'))
                <div class="rounded border border-telemetry-emerald/20 bg-telemetry-emerald/10 px-4 py-3 text-sm font-semibold text-telemetry-emerald-deep">
                    {{ session('status') }}
                </div>
            @endif

            {{-- Add goal form --}}
            <x-card>
                <x-section-heading title="➕ Tambah Goal" />
                <form method="POST" action="{{ route('goals.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="type" value="Jenis Goal" />
                        <select id="type" name="type" x-model="type" required
                            class="mt-1 block w-full rounded border border-telemetry-line bg-telemetry-surface text-telemetry-ink focus:border-telemetry-ember focus:ring-telemetry-ember">
                            @foreach ($goalTypes as $t)
                                <option value="{{ $t['type'] }}" @selected(old('type', 'weekly_distance') === $t['type'])>{{ $t['label'] }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('type')" class="mt-2" />
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="target_value" value="Target" />
                            <x-text-input id="target_value" type="number" step="0.01" min="0" name="target_value"
                                :value="old('target_value')" placeholder="mis. 30" />
                            <p class="mt-1.5 text-xs text-telemetry-slate" x-text="type === 'custom' ? 'Tidak diperlukan untuk target kustom.' : (type === 'race' ? 'Jarak lomba (km)' : (type === 'weekly_frequency' ? 'Jumlah latihan / minggu' : 'Jarak (km)'))"></p>
                            <x-input-error :messages="$errors->get('target_value')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="target_date" value="Target Selesai (opsional)" />
                            <x-text-input id="target_date" type="date" name="target_date" :value="old('target_date')" />
                            <x-input-error :messages="$errors->get('target_date')" class="mt-2" />
                        </div>
                    </div>

                    <div x-show="type === 'custom'" x-cloak>
                        <x-input-label for="custom_description" value="Deskripsi" />
                        <x-text-input id="custom_description" type="text" name="custom_description" :value="old('custom_description')"
                            placeholder="mis. PR 10K di bawah 50 menit" />
                        <x-input-error :messages="$errors->get('custom_description')" class="mt-2" />
                    </div>

                    <div>
                        <x-primary-button>Simpan Goal</x-primary-button>
                    </div>
                </form>
            </x-card>

            {{-- Goals list --}}
            <div class="space-y-4">
                @forelse ($goalRows as $row)
                    @php
                        $goal = $row['goal'];
                        $p = $row['progress'];
                        $reached = $p['percent'] !== null && $p['percent'] >= 100;
                        $barColor = $reached ? 'bg-telemetry-emerald' : ($p['percent'] !== null && $p['percent'] >= 50 ? 'bg-telemetry-chrono' : 'bg-telemetry-ember');
                    @endphp
                    <x-card>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <p class="font-bold text-telemetry-ink">{{ $p['label'] }}</p>
                                    @if ($reached)
                                        <x-chip variant="recovery">Tercapai</x-chip>
                                    @endif
                                    @if ($goal->target_date)
                                        <span class="text-xs text-telemetry-slate">· target {{ $goal->target_date->translatedFormat('d M Y') }}</span>
                                    @endif
                                </div>
                                @if ($goal->custom_description)
                                    <p class="mt-0.5 text-sm text-telemetry-slate">{{ $goal->custom_description }}</p>
                                @endif
                                <p class="mt-1 text-sm text-telemetry-slate">
                                    @if ($p['current'] !== null)
                                        <span class="telemetry-value">{{ $p['current_text'] }}</span> / {{ $p['target_text'] }}
                                    @else
                                        {{ $p['target_text'] }}
                                    @endif
                                </p>
                            </div>

                            <div class="flex flex-col items-end gap-2">
                                <p class="telemetry-value text-2xl">{{ $p['percent'] !== null ? $p['percent'].'%' : '--' }}</p>
                                <button type="button"
                                    onclick="if (confirm('Hapus goal ini?')) document.getElementById('delete-goal-{{ $goal->id }}').submit();"
                                    class="text-xs font-semibold text-telemetry-ember-deep hover:underline">Hapus</button>
                                <form id="delete-goal-{{ $goal->id }}" method="POST" action="{{ route('goals.destroy', $goal) }}" class="hidden">
                                    @csrf
                                    @method('DELETE')
                                </form>
                            </div>
                        </div>

                        @if ($p['percent'] !== null)
                            <div class="mt-3 h-2.5 w-full overflow-hidden bg-telemetry-well">
                                <div class="h-full transition-all {{ $barColor }}" style="width: {{ $p['percent'] }}%"></div>
                            </div>
                        @endif
                    </x-card>
                @empty
                    <x-card class="text-center py-12">
                        <p class="text-lg font-bold text-telemetry-ink">Belum Ada Goal</p>
                        <p class="mt-2 text-telemetry-slate">Tambahkan goal latihanmu di atas — mis. jarak mingguan 30 km atau target lomba.</p>
                    </x-card>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
