<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 rounded-full bg-gradient-to-r from-raga-accent to-raga-primary px-6 py-2.5 text-sm font-bold text-white shadow-glow transition duration-150 ease-in-out hover:brightness-110 hover:shadow-lg active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-raga-primary focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed']) }}>
    {{ $slot }}
</button>
