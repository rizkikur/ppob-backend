<x-admin.layouts.app>
    <div x-data="dashboardPoller()" x-init="init()" class="space-y-6">
        {{-- Header Status Bar --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-white">Monitoring Operasional Realtime</h1>
                <p class="mt-1 text-sm text-slate-400">Selamat datang, {{ $admin->name }}. Ringkasan transaksi dan infrastruktur sistem terkini.</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 text-slate-300 text-xs font-mono">
                    <span class="w-2 h-2 rounded-full" :class="isLive ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400'"></span>
                    <span x-text="isLive ? 'Auto-Sync: 10s' : 'Paused'">Auto-Sync: 10s</span>
                </span>
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
                timer: null,
                isLive: true,
                isRefreshing: false,
                metrics: @json($metrics),
                providers: @json($providers),
                chartData: @json($chartData),
                chartInstance: null,

                init() {
                    this.initChart();
                    this.startPolling();

                    window.addEventListener('seamless:before-swap', () => {
                        if (this.timer) {
                            clearInterval(this.timer);
                            this.timer = null;
                        }
                        if (this.chartInstance) {
                            try { this.chartInstance.destroy(); } catch (e) {}
                            this.chartInstance = null;
                        }
                    }, { once: true });
                },

                startPolling() {
                    // Polling interval 10 detik
                    this.timer = setInterval(() => {
                        if (document.visibilityState === 'visible') {
                            this.fetchMetrics();
                        }
                    }, 10000);

                    // Pause saat user pindah tab, resume otomatis saat kembali
                    document.addEventListener('visibilitychange', () => {
                        if (document.visibilityState === 'visible') {
                            this.isLive = true;
                            this.fetchMetrics();
                        } else {
                            this.isLive = false;
                        }
                    });
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
