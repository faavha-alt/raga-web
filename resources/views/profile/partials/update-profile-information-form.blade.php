<section>
    <header>
        <h2 class="text-lg font-bold text-gray-900 dark:text-white">
            Profil Atlet
        </h2>

        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Identitas publikmu di RAGA. Data kesehatan (HRV, tidur, stress, recovery) tidak pernah ikut ditampilkan.
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <!-- Avatar -->
        <div>
            <x-input-label value="Foto Profil" />
            <div class="mt-2 flex items-center gap-4">
                @if ($user->avatar_path)
                    <img src="{{ asset($user->avatar_path) }}" alt="Foto profil {{ $user->name }}" class="h-16 w-16 rounded-full object-cover ring-2 ring-white dark:ring-gray-800" />
                @else
                    <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-raga-accent to-raga-primary text-xl font-bold text-white">
                        {{ $user->initials() }}
                    </span>
                @endif

                <div class="min-w-0 flex-1">
                    <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp"
                           class="block w-full cursor-pointer rounded-2xl border border-gray-200 bg-white text-sm text-gray-500 file:mr-3 file:cursor-pointer file:rounded-l-2xl file:border-0 file:bg-gray-50 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-gray-700 hover:file:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:file:bg-gray-800 dark:file:text-gray-200" />
                    <p class="mt-1.5 text-xs text-gray-500">JPG, PNG, atau WebP. Maksimal 2 MB.</p>
                </div>
            </div>
            <x-input-error class="mt-2" :messages="$errors->get('avatar')" />
        </div>

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="username" value="Username" />
            <div class="relative mt-1">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm font-semibold text-gray-400">@</span>
                <x-text-input id="username" name="username" type="text" class="block w-full pl-8" :value="old('username', $user->username)" required autocomplete="username" />
            </div>
            @if ($user->username && \Illuminate\Support\Facades\Route::has('athletes.show'))
                <p class="mt-1.5 text-xs text-gray-500">
                    Profil publikmu:
                    <a href="{{ route('athletes.show', $user->username) }}" class="font-semibold text-raga-primary hover:text-raga-accent">{{ '@'.$user->username }}</a>
                </p>
            @endif
            <x-input-error class="mt-2" :messages="$errors->get('username')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div>
            <x-input-label for="location" value="Lokasi" />
            <x-text-input id="location" name="location" type="text" class="mt-1 block w-full" :value="old('location', $user->location)" placeholder="mis. Surakarta, Indonesia" />
            <x-input-error class="mt-2" :messages="$errors->get('location')" />
        </div>

        <div>
            <x-input-label for="bio" value="Bio" />
            <textarea id="bio" name="bio" rows="3" maxlength="300"
                      class="mt-1 block w-full rounded-2xl border-gray-200 shadow-sm focus:border-raga-primary focus:ring-raga-primary dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                      placeholder="Ceritakan target latihanmu">{{ old('bio', $user->bio) }}</textarea>
            <p class="mt-1.5 text-xs text-gray-500">Maksimal 300 karakter.</p>
            <x-input-error class="mt-2" :messages="$errors->get('bio')" />
        </div>

        <div class="rounded-2xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900/60">
            <label for="is_public" class="flex cursor-pointer items-start gap-3">
                <input id="is_public" name="is_public" type="checkbox" value="1" @checked(old('is_public', $user->is_public))
                       class="mt-0.5 rounded border-gray-300 text-raga-primary focus:ring-raga-primary" />
                <span>
                    <span class="block text-sm font-semibold text-gray-800 dark:text-gray-200">Profil dapat dilihat publik</span>
                    <span class="mt-0.5 block text-xs text-gray-500">
                        Bila dimatikan, hanya pengikutmu yang bisa melihat aktivitas dan statistikmu.
                        Aktivitas yang ditandai privat tetap privat dalam keadaan apa pun.
                    </span>
                </span>
            </label>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
