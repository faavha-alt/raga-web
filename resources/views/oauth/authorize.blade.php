<x-guest-layout>
    <div class="mb-6 border-b border-telemetry-line pb-5">
        <h1 class="telemetry-value text-2xl text-telemetry-ink">Izinkan akses?</h1>
        <p class="mt-1 text-sm text-telemetry-slate">
            <span class="font-semibold text-telemetry-ink">{{ $client->name }}</span> ingin membaca data RAGA milik
            <span class="font-semibold text-telemetry-ink">{{ $user->name }}</span> (training, recovery, health, running, trail).
        </p>
    </div>

    <div class="mb-6 rounded border border-telemetry-line bg-telemetry-well px-4 py-3 text-sm text-telemetry-slate">
        Aplikasi ini hanya akan bisa <strong>membaca</strong> data kamu, tidak bisa mengubah atau menghapus apa pun.
    </div>

    <div class="flex gap-3">
        <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="flex-1">
            @csrf
            <input type="hidden" name="state" value="{{ request('state') }}">
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <x-primary-button class="w-full justify-center">Izinkan</x-primary-button>
        </form>

        <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="flex-1">
            @csrf
            @method('DELETE')
            <input type="hidden" name="state" value="{{ request('state') }}">
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <x-secondary-button class="w-full justify-center">Tolak</x-secondary-button>
        </form>
    </div>
</x-guest-layout>
