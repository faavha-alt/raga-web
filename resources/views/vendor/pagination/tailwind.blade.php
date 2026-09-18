{{--
    Paginasi gaya Swiss Telemetry Sport — menggantikan view `pagination::tailwind`
    bawaan Laravel yang masih memakai palet abu-abu dan varian mode gelap.
--}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Navigasi halaman" class="flex flex-wrap items-center justify-between gap-3 border-t border-telemetry-line pt-4">
        <p class="telemetry-label">
            Halaman {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
            <span class="ml-2 text-telemetry-slate/70">{{ $paginator->total() }} data</span>
        </p>

        <div class="flex items-center gap-1">
            {{-- Sebelumnya --}}
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" class="inline-flex cursor-not-allowed items-center rounded border border-telemetry-line px-3 py-1.5 font-display text-[10px] font-bold uppercase tracking-[0.08em] text-telemetry-slate/40">
                    &lsaquo; Sebelumnya
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center min-h-11 sm:min-h-0 rounded border border-telemetry-line px-3 py-1.5 font-display text-[10px] font-bold uppercase tracking-[0.08em] text-telemetry-slate transition-colors hover:border-telemetry-ink hover:bg-telemetry-well hover:text-telemetry-ink">
                    &lsaquo; Sebelumnya
                </a>
            @endif

            {{-- Nomor halaman --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-2 font-display text-xs font-bold text-telemetry-slate">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="inline-flex min-w-8 items-center justify-center rounded bg-telemetry-ink px-2 py-1.5 font-display text-xs font-bold tabular-nums text-white">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="inline-flex min-w-8 items-center justify-center min-h-11 sm:min-h-0 rounded border border-telemetry-line px-2 py-1.5 font-display text-xs font-bold tabular-nums text-telemetry-slate transition-colors hover:border-telemetry-ink hover:bg-telemetry-well hover:text-telemetry-ink">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Berikutnya --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center min-h-11 sm:min-h-0 rounded border border-telemetry-line px-3 py-1.5 font-display text-[10px] font-bold uppercase tracking-[0.08em] text-telemetry-slate transition-colors hover:border-telemetry-ink hover:bg-telemetry-well hover:text-telemetry-ink">
                    Berikutnya &rsaquo;
                </a>
            @else
                <span aria-disabled="true" class="inline-flex cursor-not-allowed items-center rounded border border-telemetry-line px-3 py-1.5 font-display text-[10px] font-bold uppercase tracking-[0.08em] text-telemetry-slate/40">
                    Berikutnya &rsaquo;
                </span>
            @endif
        </div>
    </nav>
@endif
