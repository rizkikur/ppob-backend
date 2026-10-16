<div x-data="{
        dateStart: '{{ request('date_start', $filters['date_start'] ?? '') }}',
        dateEnd: '{{ request('date_end', $filters['date_end'] ?? '') }}',
        onStartChange(val) {
            this.dateStart = val;
            if (this.dateEnd && this.dateStart > this.dateEnd) {
                this.dateEnd = this.dateStart;
            }
        },
        onEndChange(val) {
            this.dateEnd = val;
            if (this.dateStart && this.dateEnd < this.dateStart) {
                this.dateStart = this.dateEnd;
            }
        },
        setToday() {
            const today = new Date().toISOString().split('T')[0];
            this.dateStart = today;
            this.dateEnd = today;
        },
        setLast7Days() {
            const end = new Date();
            const start = new Date();
            start.setDate(end.getDate() - 6);
            this.dateStart = start.toISOString().split('T')[0];
            this.dateEnd = end.toISOString().split('T')[0];
        },
        setThisMonth() {
            const now = new Date();
            const start = new Date(now.getFullYear(), now.getMonth(), 1);
            this.dateStart = start.toISOString().split('T')[0];
            this.dateEnd = now.toISOString().split('T')[0];
        },
        clearDates() {
            this.dateStart = '';
            this.dateEnd = '';
        }
     }"
     class="relative z-40 bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl"
     style="position: relative; z-index: 40;">
    <form data-filter-form method="GET" action="{{ route('admin.transactions.index') }}" @submit.prevent="applyFilter($event)" class="space-y-4">
        {{-- Row 1: Search & Primary Dropdowns in Flexible Row --}}
        <div class="flex flex-col lg:flex-row gap-3.5 items-stretch lg:items-end">
            {{-- Search Input (Wide Flexible Grow) --}}
            <div class="flex-1 min-w-[260px]">
                <label for="q" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Pencarian Fleksibel</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text"
                           id="q"
                           name="q"
                           value="{{ request('q', $filters['q'] ?? '') }}"
                           placeholder="ID Transaksi, No HP, Ref, Idempotency, SN..."
                           class="w-full bg-slate-950/80 border border-slate-700/80 rounded-xl pl-10 pr-3.5 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                </div>
            </div>

            {{-- Status Filter --}}
            <div class="w-full sm:w-auto lg:w-48 shrink-0">
                <label for="status" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Status Transaksi</label>
                <select id="status"
                        name="status"
                        class="w-full bg-slate-950/80 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                    <option value="">Semua Status</option>
                    @foreach(['pending' => 'Pending', 'processing' => 'Processing', 'success' => 'Success', 'failed' => 'Failed', 'refunded' => 'Refunded'] as $val => $label)
                        <option value="{{ $val }}" @selected(request('status', $filters['status'] ?? '') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Provider Filter --}}
            <div class="w-full sm:w-auto lg:w-48 shrink-0">
                <label for="provider_id" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Provider Gateway</label>
                <select id="provider_id"
                        name="provider_id"
                        class="w-full bg-slate-950/80 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                    <option value="">Semua Provider</option>
                    @foreach($providers as $provider)
                        <option value="{{ $provider->id }}" @selected((string)request('provider_id', $filters['provider_id'] ?? '') === (string)$provider->id)>
                            {{ $provider->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Failover Route Filter --}}
            <div class="w-full sm:w-auto lg:w-44 shrink-0">
                <label for="is_failover" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Rute Routing</label>
                <select id="is_failover"
                        name="is_failover"
                        class="w-full bg-slate-950/80 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                    <option value="">Semua Rute</option>
                    <option value="0" @selected(request('is_failover', $filters['is_failover'] ?? '') === '0')>Primary Saja</option>
                    <option value="1" @selected(request('is_failover', $filters['is_failover'] ?? '') === '1')>⚡ Failover Saja</option>
                </select>
            </div>
        </div>

        {{-- Row 2: Date Filters & Action Toolbar in Flexible Row --}}
        <div class="flex flex-col lg:flex-row gap-3.5 pt-3 border-t border-slate-800/80 items-stretch lg:items-end justify-between">
            {{-- Date Filters Left Group with Quick Presets --}}
            <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                    <span class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Rentang Tanggal</span>
                    <div class="inline-flex items-center gap-1 text-[11px]">
                        <button type="button" @click="setToday()" class="px-2 py-0.5 rounded bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 transition-colors">Hari Ini</button>
                        <button type="button" @click="setLast7Days()" class="px-2 py-0.5 rounded bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 transition-colors">7 Hari</button>
                        <button type="button" @click="setThisMonth()" class="px-2 py-0.5 rounded bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 transition-colors">Bulan Ini</button>
                        <button type="button" @click="clearDates()" class="px-2 py-0.5 rounded bg-slate-800/60 text-slate-400 hover:text-slate-200 hover:bg-slate-700 transition-colors">Semua</button>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
                    <div class="w-full sm:w-44">
                        <input type="date"
                               id="date_start"
                               name="date_start"
                               x-model="dateStart"
                               @change="onStartChange($event.target.value)"
                               @click="$el.showPicker && $el.showPicker()"
                               :max="dateEnd ? dateEnd : null"
                               class="w-full bg-slate-950/80 border border-slate-700/80 rounded-xl px-3 py-2 text-sm text-slate-100 cursor-pointer focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                    </div>
                    <span class="text-xs text-slate-500 text-center font-medium">s/d</span>
                    <div class="w-full sm:w-44">
                        <input type="date"
                               id="date_end"
                               name="date_end"
                               x-model="dateEnd"
                               @change="onEndChange($event.target.value)"
                               @click="$el.showPicker && $el.showPicker()"
                               :min="dateStart ? dateStart : null"
                               class="w-full bg-slate-950/80 border border-slate-700/80 rounded-xl px-3 py-2 text-sm text-slate-100 cursor-pointer focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                    </div>
                </div>

                @error('date_end')
                    <p class="text-xs text-rose-400 mt-1 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>{{ $message }}</span>
                    </p>
                @enderror
            </div>

            {{-- Action Buttons Right Group --}}
            <div class="flex items-center justify-between sm:justify-end gap-2.5 flex-wrap pt-2 lg:pt-0">
                {{-- Live Stream SSE Toggle Button --}}
                <button type="button"
                        @click="toggleLive()"
                        :class="liveEnabled ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30 ring-1 ring-emerald-500/20 shadow-lg shadow-emerald-500/10' : 'bg-slate-800/80 text-slate-400 border-slate-700/80 hover:text-slate-200'"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border text-xs font-semibold transition-all"
                        :title="liveEnabled ? 'Monitoring Realtime SSE Aktif' : 'Aktifkan Monitoring Realtime SSE'">
                    <span class="relative flex h-2 w-2">
                        <span x-show="liveEnabled" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span :class="liveEnabled ? 'bg-emerald-500' : 'bg-slate-500'" class="relative inline-flex rounded-full h-2 w-2"></span>
                    </span>
                    <span x-text="liveEnabled ? '⚡ Live SSE: ON' : 'Live SSE: OFF'">Live SSE: OFF</span>
                </button>

                {{-- Manual Refresh Button --}}
                <button type="button"
                        @click="fetchTable()"
                        :disabled="isFetching"
                        title="Perbarui data tabel sekarang"
                        class="p-2 rounded-xl bg-slate-800/80 text-slate-400 hover:text-white border border-slate-700/80 hover:bg-slate-700 transition-all">
                    <svg :class="isFetching ? 'animate-spin text-indigo-400' : ''" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </button>

                {{-- Reset Filter Button --}}
                <button type="button"
                        @click.prevent="resetFilter('{{ route('admin.transactions.index') }}')"
                        class="px-3 py-2 text-xs font-medium text-slate-400 hover:text-white hover:bg-slate-800 rounded-xl border border-transparent hover:border-slate-700 transition-all">
                    Reset Filter
                </button>

                {{-- Export CSV Dropdown Menu --}}
                <div class="relative z-50" x-data="{ open: false }" @click.outside="open = false" style="position: relative; z-index: 50;">
                    <button type="button"
                            @click="open = !open"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white border border-slate-700 rounded-xl text-xs font-semibold transition-all">
                        <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span>Export CSV</span>
                        <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div x-show="open" x-cloak
                         class="absolute right-0 mt-2 w-72 rounded-2xl bg-slate-900 border border-slate-700/80 shadow-2xl py-2 z-50"
                         style="z-index: 100;">
                        <div class="px-3.5 py-1.5 border-b border-slate-800 text-[10px] uppercase font-bold text-slate-400 tracking-wider">
                            Pilihan Unduhan CSV
                        </div>
                        <button type="button"
                                @click="open = false; startAsyncExport()"
                                class="w-full text-left px-3.5 py-2.5 text-xs text-slate-200 hover:bg-indigo-600/20 hover:text-indigo-300 flex items-start gap-2.5 transition-colors">
                            <svg class="w-4 h-4 text-indigo-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            <div>
                                <span class="font-semibold block text-slate-100">Export Latar Belakang (Async)</span>
                                <span class="text-[11px] text-slate-400">Rekomendasi untuk ribuan baris, anti-timeout dengan progress bar live.</span>
                            </div>
                        </button>
                        <button type="button"
                                @click="open = false; exportCsv('{{ route('admin.transactions.index') }}')"
                                class="w-full text-left px-3.5 py-2.5 text-xs text-slate-200 hover:bg-slate-800 hover:text-white flex items-start gap-2.5 transition-colors">
                            <svg class="w-4 h-4 text-emerald-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            <div>
                                <span class="font-semibold block text-slate-100">Export Langsung (Sinkron)</span>
                                <span class="text-[11px] text-slate-400">Unduh langsung untuk data sedikit (&lt; 500 baris).</span>
                            </div>
                        </button>
                        <div class="border-t border-slate-800 my-1"></div>
                        <button type="button"
                                @click="open = false; fetchRecentExports()"
                                class="w-full text-left px-3.5 py-2 text-xs text-slate-300 hover:bg-slate-800 hover:text-white flex items-center gap-2 transition-colors">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Riwayat Unduhan Export</span>
                        </button>
                    </div>
                </div>

                {{-- Apply Filter Button --}}
                <button type="submit"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl shadow-lg shadow-indigo-600/30 transition-all flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    <span>Filter</span>
                </button>
            </div>
        </div>
    </form>
</div>
