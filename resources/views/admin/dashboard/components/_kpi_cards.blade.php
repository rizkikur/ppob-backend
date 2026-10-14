<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
    {{-- Card 1: GMV Hari Ini --}}
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 relative overflow-hidden shadow-sm">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">GMV Hari Ini</span>
            <span class="p-2.5 rounded-xl bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
        </div>
        <div class="mt-4">
            <div class="text-2xl font-bold text-white tracking-tight" x-text="formatRupiah(metrics.gmv_today)">
                Rp {{ number_format($metrics['gmv_today'] ?? 0, 0, ',', '.') }}
            </div>
            <p class="mt-1 text-xs text-slate-400 flex items-center gap-1.5">
                <span class="text-emerald-400 font-medium">Omzet Sukses</span>
                <span>• Realtime</span>
            </p>
        </div>
    </div>

    {{-- Card 2: Gross Profit --}}
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 relative overflow-hidden shadow-sm">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Gross Profit</span>
            <span class="p-2.5 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            </span>
        </div>
        <div class="mt-4">
            @if(is_null($metrics['profit_today']))
                <div class="text-2xl font-bold text-slate-500 tracking-wider">
                    Rp ***
                </div>
                <p class="mt-1 text-xs text-slate-500">
                    Disamarkan (Akses Terbatas Peran)
                </p>
            @else
                <div class="text-2xl font-bold text-emerald-400 tracking-tight" x-text="formatRupiah(metrics.profit_today)">
                    Rp {{ number_format($metrics['profit_today'] ?? 0, 0, ',', '.') }}
                </div>
                <p class="mt-1 text-xs text-slate-400">
                    Margin Bersih Hari Ini
                </p>
            @endif
        </div>
    </div>

    {{-- Card 3: Total Transaksi --}}
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 relative overflow-hidden shadow-sm">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Volume Transaksi</span>
            <span class="p-2.5 rounded-xl bg-sky-500/10 text-sky-400 border border-sky-500/20">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </span>
        </div>
        <div class="mt-4">
            <div class="text-2xl font-bold text-white tracking-tight" x-text="metrics.transactions_count">
                {{ number_format($metrics['transactions_count'] ?? 0) }}
            </div>
            <div class="mt-1 flex items-center gap-2 text-xs text-slate-400">
                <span class="text-emerald-400 font-medium" x-text="`${metrics.success_count} Sukses`">{{ $metrics['success_count'] ?? 0 }} Sukses</span>
                <span>•</span>
                <span class="text-rose-400 font-medium" x-text="`${metrics.failed_count} Gagal`">{{ $metrics['failed_count'] ?? 0 }} Gagal</span>
            </div>
        </div>
    </div>

    {{-- Card 4: Success Rate --}}
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 relative overflow-hidden shadow-sm">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Success Rate</span>
            <span class="p-2.5 rounded-xl bg-violet-500/10 text-violet-400 border border-violet-500/20">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
        </div>
        <div class="mt-4">
            <div class="text-2xl font-bold text-white tracking-tight" x-text="`${metrics.success_rate}%`">
                {{ $metrics['success_rate'] ?? 0 }}%
            </div>
            <div class="mt-2 w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                <div class="bg-indigo-500 h-1.5 rounded-full transition-all duration-500"
                     :style="`width: ${metrics.success_rate}%`"
                     style="width: {{ $metrics['success_rate'] ?? 0 }}%"></div>
            </div>
        </div>
    </div>
</div>
