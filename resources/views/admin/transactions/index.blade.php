<x-admin.layouts.app>
    <div x-data="transactionLiveMonitor()" x-init="initMonitor()" class="space-y-6">
        {{-- Flash Alerts --}}
        @if(session('success'))
            <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>{{ session('success') }}</span>
                </div>
                <button type="button" @click="$el.parentElement.remove()" class="text-emerald-400/70 hover:text-emerald-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @endif

        @if(session('error'))
            <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span>{{ session('error') }}</span>
                </div>
                <button type="button" @click="$el.parentElement.remove()" class="text-rose-400/70 hover:text-rose-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @endif

        {{-- Page Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-white">Manajemen & Monitoring Transaksi</h1>
                <p class="mt-1 text-sm text-slate-400">Kelola riwayat transaksi PPOB, pantau status upstream, dan lakukan intervensi operasional.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.dashboard') }}"
                   class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700/60 text-slate-300 hover:text-white text-xs font-semibold transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <span>Ke Dashboard</span>
                </a>
            </div>
        </div>

        {{-- Filter & Search Bar --}}
        @include('admin.transactions.components._filter_bar')

        {{-- Transactions Table Container for Live Auto-Refresh --}}
        <div id="transaction-table-container" class="relative">
            {{-- Loading Overlay --}}
            <div x-show="isFetching" x-cloak
                 class="absolute inset-0 z-20 bg-slate-950/40 backdrop-blur-[1px] flex items-center justify-center rounded-2xl transition-all">
                <div class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-900/90 border border-indigo-500/40 text-indigo-300 text-xs font-medium shadow-2xl">
                    <svg class="w-4 h-4 animate-spin text-indigo-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span>Memperbarui data transaksi...</span>
                </div>
            </div>

            @include('admin.transactions.components._transaction_table')
        </div>
    </div>

    {{-- Live Polling Script --}}
    <script nonce="{{ $csp_nonce ?? '' }}">
        function transactionLiveMonitor() {
            return {
                liveEnabled: false,
                countdown: 15,
                intervalTimer: null,
                isFetching: false,
                lastUpdated: 'Baru saja',

                initMonitor() {
                    document.addEventListener('visibilitychange', () => {
                        if (document.hidden && this.liveEnabled) {
                            this.pausePolling();
                        } else if (!document.hidden && this.liveEnabled) {
                            this.startPolling();
                        }
                    });
                },

                toggleLive() {
                    this.liveEnabled = !this.liveEnabled;
                    if (this.liveEnabled) {
                        this.countdown = 15;
                        this.startPolling();
                    } else {
                        this.pausePolling();
                    }
                },

                startPolling() {
                    this.clearTimer();
                    this.intervalTimer = setInterval(() => {
                        if (document.hidden) return;
                        this.countdown--;
                        if (this.countdown <= 0) {
                            this.fetchTable();
                            this.countdown = 15;
                        }
                    }, 1000);
                },

                pausePolling() {
                    this.clearTimer();
                },

                clearTimer() {
                    if (this.intervalTimer) {
                        clearInterval(this.intervalTimer);
                        this.intervalTimer = null;
                    }
                },

                async fetchTable() {
                    if (this.isFetching) return;
                    this.isFetching = true;
                    try {
                        const url = new URL(window.location.href);
                        url.searchParams.set('live', '1');

                        const res = await fetch(url.toString(), {
                            headers: {
                                'X-Live-Refresh': '1',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });

                        if (res.ok) {
                            const html = await res.text();
                            const container = document.getElementById('transaction-table-container');
                            if (container) {
                                // Preserve overlay and replace inner table
                                const parser = new DOMParser();
                                const doc = parser.parseFromString(html, 'text/html');
                                const newTable = doc.body.firstElementChild;
                                if (newTable) {
                                    const oldTable = container.querySelector('.bg-slate-800\\/80');
                                    if (oldTable) {
                                        oldTable.replaceWith(newTable);
                                    } else {
                                        container.appendChild(newTable);
                                    }
                                }
                                const now = new Date();
                                this.lastUpdated = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                            }
                        }
                    } catch (e) {
                        console.error('Gagal memperbarui data transaksi:', e);
                    } finally {
                        this.isFetching = false;
                    }
                }
            };
        }
    </script>
</x-admin.layouts.app>
