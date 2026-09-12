@once
    <script>
        /**
         * Tombol kudos: update optimistis + fetch POST/DELETE ke kudos.store /
         * kudos.destroy. Kalau request gagal, tampilan dikembalikan ke keadaan
         * semula (graceful failure).
         */
        document.addEventListener('alpine:init', () => {
            Alpine.data('activityKudos', (config) => ({
                given: config.given,
                count: config.count,
                pending: false,
                error: false,

                async toggle() {
                    if (this.pending) return;

                    this.pending = true;
                    this.error = false;

                    const wasGiven = this.given;
                    this.given = !wasGiven;
                    this.count += wasGiven ? -1 : 1;

                    try {
                        const response = await fetch(wasGiven ? config.destroyUrl : config.storeUrl, {
                            method: wasGiven ? 'DELETE' : 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (! response.ok) {
                            throw new Error('Kudos gagal disimpan.');
                        }
                    } catch (e) {
                        // Kembalikan tampilan optimistis ke keadaan semula.
                        this.given = wasGiven;
                        this.count += wasGiven ? 1 : -1;
                        this.error = true;
                    } finally {
                        this.pending = false;
                    }
                },
            }));
        });
    </script>
@endonce
