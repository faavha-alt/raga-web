<x-guest-layout>
    <div class="mb-4 border-b border-telemetry-line pb-5 text-sm text-telemetry-slate">
        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 border border-telemetry-emerald/20 bg-telemetry-emerald/10 px-4 py-3 text-sm font-semibold text-telemetry-emerald-deep">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between gap-4">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('Resend Verification Email') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="inline-flex items-center min-h-11 sm:min-h-0 font-display text-[11px] font-bold uppercase tracking-[0.08em] text-telemetry-slate underline decoration-telemetry-line transition-colors hover:text-telemetry-ink focus:outline-none focus:ring-2 focus:ring-telemetry-ink">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
