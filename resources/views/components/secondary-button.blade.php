<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center gap-2 min-h-11 sm:min-h-0 rounded border border-telemetry-line-strong bg-telemetry-surface px-5 py-2.5 font-display text-[11px] font-bold uppercase tracking-[0.08em] text-telemetry-ink transition-colors hover:border-telemetry-ink hover:bg-telemetry-well focus:outline-none focus-visible:ring-2 focus-visible:ring-telemetry-ink focus-visible:ring-offset-2 disabled:opacity-40']) }}>
    {{ $slot }}
</button>
