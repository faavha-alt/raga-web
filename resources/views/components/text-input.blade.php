@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full min-h-11 sm:min-h-0 rounded border border-telemetry-line-strong bg-telemetry-surface px-3 py-2 text-sm font-medium text-telemetry-ink placeholder:text-telemetry-slate/60 focus:border-telemetry-ink focus:outline-none focus:ring-0 transition-colors']) }}>
