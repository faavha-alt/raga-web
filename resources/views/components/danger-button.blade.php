<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 rounded bg-telemetry-ember-deep px-5 py-2.5 font-display text-[11px] font-bold uppercase tracking-[0.08em] text-white transition-colors hover:bg-telemetry-ember-deep/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-telemetry-ember focus-visible:ring-offset-2 disabled:opacity-50']) }}>
    {{ $slot }}
</button>
