<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-extrabold text-gray-900">Izinkan akses?</h1>
        <p class="mt-1 text-sm text-gray-500">
            <span class="font-semibold text-gray-700">{{ $client->name }}</span> ingin membaca data RAGA milik
            <span class="font-semibold text-gray-700">{{ $user->name }}</span> (training, recovery, health, running, trail).
        </p>
    </div>

    <div class="mb-6 rounded-2xl bg-gray-50 border border-gray-200 px-4 py-3 text-sm text-gray-600">
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
