<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white leading-tight">
            🤖 {{ __('AI Coach') }}
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500">Tanya apa saja soal kondisi, latihan, dan recovery kamu.</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8">
            @if (! $configured)
                <x-card class="text-center py-16">
                    <p class="text-lg font-bold text-gray-900 dark:text-gray-100">AI Coach belum diatur</p>
                    <p class="mt-2 text-gray-500 dark:text-gray-400">Masukkan API key kamu sendiri (Claude atau Gemini) untuk mulai chat.</p>
                    <a href="{{ route('settings.ai.show') }}" class="mt-5 inline-block rounded-full bg-raga-primary px-6 py-2.5 text-sm font-bold text-white hover:opacity-90 transition">
                        Atur API Key →
                    </a>
                </x-card>
            @else
                <div
                    x-data="aiChat({
                        conversationId: {{ $conversation?->id ?? 'null' }},
                        initialMessages: {{ $messages->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])->values()->toJson() }},
                    })"
                    class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-4 items-start"
                >
                    {{-- Conversation list --}}
                    <x-card class="!p-3 lg:sticky lg:top-20">
                        <a
                            href="{{ route('ai') }}"
                            class="block mb-3 rounded-2xl bg-raga-primary px-4 py-2.5 text-center text-sm font-bold text-white hover:opacity-90 transition"
                        >
                            + Percakapan Baru
                        </a>
                        <div class="space-y-1 max-h-[60vh] overflow-y-auto">
                            @forelse ($conversations as $c)
                                <a
                                    href="{{ route('ai', ['conversation' => $c->id]) }}"
                                    class="block rounded-xl px-3 py-2 text-sm truncate transition {{ $conversation?->id === $c->id ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900 font-bold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800' }}"
                                >
                                    {{ $c->title }}
                                </a>
                            @empty
                                <p class="px-3 py-2 text-sm text-gray-400">Belum ada percakapan.</p>
                            @endforelse
                        </div>
                    </x-card>

                    {{-- Chat --}}
                    <x-card class="!p-0 flex flex-col h-[75vh]">
                        <div class="flex-1 overflow-y-auto p-5 space-y-5" x-ref="scrollArea">
                            <template x-if="messages.length === 0">
                                <div class="h-full flex flex-col items-center justify-center text-center text-gray-400 gap-3">
                                    <span class="text-4xl">💬</span>
                                    <p class="max-w-xs text-sm">Coba tanya: "bagaimana kondisi saya hari ini?" atau "apakah saya harus latihan berat hari ini?"</p>
                                </div>
                            </template>

                            <template x-for="(m, i) in messages" :key="i">
                                <div :class="m.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                                    <template x-if="m.role === 'user'">
                                        <div class="max-w-[80%] rounded-2xl rounded-br-md bg-raga-primary text-white px-4 py-2.5 text-sm leading-relaxed whitespace-pre-wrap" x-text="m.content"></div>
                                    </template>
                                    <template x-if="m.role !== 'user'">
                                        <div class="ai-message max-w-[85%] rounded-2xl rounded-bl-md bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-100 px-4 py-3 text-sm" x-html="renderMarkdown(m.content)"></div>
                                    </template>
                                </div>
                            </template>

                            <div x-show="loading" x-cloak class="flex justify-start">
                                <div class="rounded-2xl rounded-bl-md bg-gray-100 dark:bg-gray-800 px-4 py-2.5 text-sm text-gray-400">
                                    Mengetik…
                                </div>
                            </div>
                        </div>

                        <form @submit.prevent="send" class="border-t border-gray-100 dark:border-gray-800 p-3 flex items-end gap-2">
                            <textarea
                                x-model="draft"
                                @keydown.enter.prevent="if (!$event.shiftKey) send()"
                                :disabled="loading"
                                rows="1"
                                placeholder="Tulis pertanyaan…"
                                class="flex-1 resize-none rounded-2xl border-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 text-sm focus:border-raga-primary focus:ring-raga-primary"
                            ></textarea>
                            <button
                                type="submit"
                                :disabled="loading || !draft.trim()"
                                class="shrink-0 rounded-full bg-raga-primary text-white px-5 py-2.5 text-sm font-bold disabled:opacity-40 hover:opacity-90 transition"
                            >
                                Kirim
                            </button>
                        </form>
                    </x-card>
                </div>

                <style>
                    .ai-message { line-height: 1.65; }
                    .ai-message > :first-child { margin-top: 0; }
                    .ai-message > :last-child { margin-bottom: 0; }
                    .ai-message p { margin: 0.6rem 0; }
                    .ai-message h4 { font-weight: 700; font-size: 0.9rem; margin: 1rem 0 0.4rem; }
                    .ai-message ul, .ai-message ol { margin: 0.5rem 0; padding-left: 1.25rem; }
                    .ai-message ul { list-style: disc; }
                    .ai-message ol { list-style: decimal; }
                    .ai-message li { margin: 0.25rem 0; }
                    .ai-message li > p { margin: 0.2rem 0; }
                    .ai-message strong { font-weight: 700; }
                    .ai-message code { font-size: 0.85em; background: rgba(0, 0, 0, 0.06); padding: 0.1em 0.35em; border-radius: 0.35rem; }
                    .dark .ai-message code { background: rgba(255, 255, 255, 0.1); }
                </style>

                <script>
                    function aiChat({ conversationId, initialMessages }) {
                        return {
                            conversationId,
                            messages: initialMessages,
                            draft: '',
                            loading: false,
                            renderMarkdown(src) {
                                const esc = (s) => s
                                    .replace(/&/g, '&amp;')
                                    .replace(/</g, '&lt;')
                                    .replace(/>/g, '&gt;');

                                const inline = (s) => esc(s)
                                    .replace(/`([^`]+)`/g, '<code>$1</code>')
                                    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
                                    .replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, '$1<em>$2</em>');

                                const lines = (src || '').replace(/\r\n/g, '\n').split('\n');
                                const out = [];
                                let list = null;
                                let para = [];

                                const flushPara = () => {
                                    if (para.length) {
                                        out.push('<p>' + para.map(inline).join('<br>') + '</p>');
                                        para = [];
                                    }
                                };
                                const flushList = () => {
                                    if (list) { out.push('</' + list + '>'); list = null; }
                                };

                                for (const raw of lines) {
                                    const line = raw.trimEnd();
                                    if (!line.trim()) { flushPara(); flushList(); continue; }

                                    let m;
                                    if ((m = line.match(/^\s*#{1,6}\s+(.*)$/))) {
                                        flushPara(); flushList();
                                        out.push('<h4>' + inline(m[1]) + '</h4>');
                                    } else if ((m = line.match(/^\s*[-*]\s+(.*)$/))) {
                                        flushPara();
                                        if (list !== 'ul') { flushList(); out.push('<ul>'); list = 'ul'; }
                                        out.push('<li>' + inline(m[1]) + '</li>');
                                    } else if ((m = line.match(/^\s*\d+[.)]\s+(.*)$/))) {
                                        flushPara();
                                        if (list !== 'ol') { flushList(); out.push('<ol>'); list = 'ol'; }
                                        out.push('<li>' + inline(m[1]) + '</li>');
                                    } else {
                                        flushList();
                                        para.push(line);
                                    }
                                }
                                flushPara();
                                flushList();
                                return out.join('');
                            },
                            async send() {
                                const text = this.draft.trim();
                                if (!text || this.loading) return;

                                this.messages.push({ role: 'user', content: text });
                                this.draft = '';
                                this.loading = true;
                                this.$nextTick(() => this.scrollToBottom());

                                try {
                                    const res = await fetch('{{ route('ai.messages.store') }}', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'Accept': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                        },
                                        body: JSON.stringify({ message: text, conversation_id: this.conversationId }),
                                    });

                                    const data = await res.json();

                                    if (!res.ok) {
                                        const msg = data.error === 'not_configured'
                                            ? data.message + ' (buka Settings > AI Coach)'
                                            : (data.message || 'Maaf, ada masalah saat menghubungi AI Coach. Coba lagi sebentar lagi.');
                                        this.messages.push({ role: 'assistant', content: msg });
                                        return;
                                    }

                                    this.conversationId = data.conversation_id;
                                    this.messages.push({ role: 'assistant', content: data.reply });

                                    if (!window.location.search.includes('conversation=')) {
                                        window.history.replaceState({}, '', '{{ route('ai') }}?conversation=' + data.conversation_id);
                                    }
                                } catch (e) {
                                    this.messages.push({ role: 'assistant', content: 'Maaf, ada masalah saat menghubungi AI Coach. Coba lagi sebentar lagi.' });
                                } finally {
                                    this.loading = false;
                                    this.$nextTick(() => this.scrollToBottom());
                                }
                            },
                            scrollToBottom() {
                                this.$refs.scrollArea.scrollTop = this.$refs.scrollArea.scrollHeight;
                            },
                        };
                    }
                </script>
            @endif
        </div>
    </div>
</x-app-layout>
