<x-admin.layouts.app>
    <div x-data="dashboardPoller()" x-init="init()" class="space-y-6">
        {{-- Header Status Bar --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-white">Monitoring Operasional Realtime</h1>
                <p class="mt-1 text-sm text-slate-400">Selamat datang, {{ $admin->name }}. Ringkasan transaksi dan infrastruktur sistem terkini.</p>
            </div>
            <div class="flex items-center gap-3">
                {{-- Live SSE Toggle Button --}}
                <button type="button"
                        @click="toggleLiveStream()"
                        :class="isLive ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30' : 'bg-slate-900 text-slate-400 border-slate-800 hover:text-slate-200'"
                        class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl border text-xs font-semibold transition-all">
                    <span class="relative flex h-2 w-2">
                        <span x-show="isLive" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span :class="isLive ? 'bg-emerald-500' : 'bg-slate-500'" class="relative inline-flex rounded-full h-2 w-2"></span>
                    </span>
                    <span x-text="isLive ? '⚡ Live SSE: ON' : 'Live: OFF'">⚡ Live SSE: ON</span>
                </button>

                <button @click="fetchMetrics()"
                        class="p-2 rounded-xl bg-slate-900 border border-slate-800 text-slate-400 hover:text-white hover:bg-slate-800 transition-colors"
                        title="Perbarui Manual">
                    <svg class="w-4 h-4" :class="isRefreshing ? 'animate-spin text-indigo-400' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </button>
            </div>
        </div>

        {{-- 1. KPI Cards --}}
        @include('admin.dashboard.components._kpi_cards')

        {{-- 2. Urgent Action Queues --}}
        @include('admin.dashboard.components._action_queue_cards')

        {{-- 3. Trend Chart & Provider Status --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2">
                @include('admin.dashboard.components._transaction_chart')
            </div>
            <div class="lg:col-span-1">
                @include('admin.dashboard.components._provider_status_card')
            </div>
        </div>
    </div>

    {{-- Poller & Chart Controller Script --}}
    <script nonce="{{ $csp_nonce ?? '' }}">
        function dashboardPoller() {
            return {
                eventSource: null,
                isLive: true,
                isRefreshing: false,
                metrics: @json($metrics),
                providers: @json($providers),
                chartData: @json($chartData),
                chartInstance: null,

                init() {
                    this.initChart();
                    this.startStream();

                    window.addEventListener('seamless:before-swap', () => {
                        this.stopStream();
                        if (this.chartInstance) {
                            try { this.chartInstance.destroy(); } catch (e) {}
                            this.chartInstance = null;
                        }
                    }, { once: true });

                    // Pause saat user pindah tab, resume otomatis saat kembali
                    document.addEventListener('visibilitychange', () => {
                        if (document.visibilityState === 'visible' && this.isLive) {
                            this.startStream();
                        } else {
                            this.stopStream(false);
                        }
                    });
                },

                // Buka koneksi Server-Sent Events (SSE)
                startStream() {
                    if (this.eventSource) return;

                    try {
                        this.eventSource = new EventSource('{{ route('admin.dashboard.stream') }}');

                        this.eventSource.addEventListener('initial', (e) => {
                            try {
                                const data = JSON.parse(e.data);
                                this.metrics = data.metrics;
                                this.providers = data.providers;
                            } catch (err) {}
                        });

                        this.eventSource.addEventListener('update', (e) => {
                            try {
                                const data = JSON.parse(e.data);
                                this.metrics = data.metrics;
                                this.providers = data.providers;
                            } catch (err) {}
                        });

                        this.eventSource.onerror = () => {
                            // EventSource otomatis mencoba reconnect setelah 3 detik
                        };
                    } catch (e) {
                        console.warn('SSE tidak didukung browser, fallback ke manual refresh:', e);
                    }
                },

                // Tutup koneksi SSE
                stopStream(setInactive = true) {
                    if (this.eventSource) {
                        this.eventSource.close();
                        this.eventSource = null;
                    }
                    if (setInactive) {
                        this.isLive = false;
                    }
                },

                // Toggle saklar Live SSE ON/OFF
                toggleLiveStream() {
                    this.isLive = !this.isLive;
                    if (this.isLive) {
                        this.startStream();
                    } else {
                        this.stopStream(true);
                    }
                },

                async fetchMetrics() {
                    this.isRefreshing = true;
                    try {
                        const response = await fetch('{{ route('admin.dashboard.metrics') }}', {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });

                        if (response.ok) {
                            const data = await response.json();
                            this.metrics = data.metrics;
                            this.providers = data.providers;
                        }
                    } catch (e) {
                        console.warn('Gagal memuat metrik realtime:', e);
                    } finally {
                        this.isRefreshing = false;
                    }
                },

                formatRupiah(value) {
                    if (value === null || value === undefined) return 'Rp 0';
                    return 'Rp ' + Number(value).toLocaleString('id-ID');
                },

                initChart() {
                    const ctx = document.getElementById('hourlyTrendChart');
                    if (!ctx || typeof Chart === 'undefined') return;

                    this.chartInstance = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: this.chartData.labels,
                            datasets: [
                                {
                                    label: 'Sukses',
                                    data: this.chartData.success,
                                    borderColor: '#6366f1',
                                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                                    borderWidth: 2,
                                    tension: 0.3,
                                    fill: true,
                                    pointRadius: 2,
                                    pointHoverRadius: 5
                                },
                                {
                                    label: 'Gagal',
                                    data: this.chartData.failed,
                                    borderColor: '#f43f5e',
                                    backgroundColor: 'rgba(244, 63, 94, 0.05)',
                                    borderWidth: 2,
                                    tension: 0.3,
                                    fill: true,
                                    pointRadius: 2,
                                    pointHoverRadius: 5
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                intersect: false,
                                mode: 'index'
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: '#0f172a',
                                    titleColor: '#e2e8f0',
                                    bodyColor: '#e2e8f0',
                                    borderColor: '#334155',
                                    borderWidth: 1,
                                    padding: 10,
                                    cornerRadius: 8
                                }
                            },
                            scales: {
                                x: {
                                    grid: { color: '#1e293b' },
                                    ticks: { color: '#64748b', font: { size: 10 } }
                                },
                                y: {
                                    grid: { color: '#1e293b' },
                                    ticks: { color: '#64748b', font: { size: 10 }, stepSize: 1 },
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                }
            };
        }
    </script>
</x-admin.layouts.app>
