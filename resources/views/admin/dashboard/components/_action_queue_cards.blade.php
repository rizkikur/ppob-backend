<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    {{-- Antrean Transaksi Gantung / Pending --}}
    <div class="p-5 rounded-2xl border transition-all"
         :class="metrics.pending_transactions > 0 ? 'bg-amber-500/5 border-amber-500/30' : 'bg-slate-900 border-slate-800'">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl"
                     :class="metrics.pending_transactions > 0 ? 'bg-amber-500/10 text-amber-400' : 'bg-slate-800 text-slate-400'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-white">Transaksi Pending & Processing</h3>
                    <p class="text-xs text-slate-400">Menunggu konfirmasi final status dari gateway provider</p>
                </div>
            </div>
            <div class="text-right">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold"
                      :class="metrics.pending_transactions > 0 ? 'bg-amber-500/20 text-amber-300 animate-pulse' : 'bg-slate-800 text-slate-300'"
                      x-text="metrics.pending_transactions">
                    {{ $metrics['pending_transactions'] ?? 0 }}
                </span>
            </div>
        </div>
        <div class="mt-4 pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs">
            <span class="text-slate-400">Diperbarui realtime melalui background worker</span>
            <a href="{{ route('admin.transactions.index', ['status' => 'pending']) }}" class="text-indigo-400 hover:text-indigo-300 font-medium flex items-center gap-1">
                Buka Antrean &rarr;
            </a>
        </div>
    </div>

    {{-- Antrean Persetujuan Top-Up Manual --}}
    <div class="p-5 rounded-2xl border transition-all"
         :class="metrics.pending_topups > 0 ? 'bg-indigo-500/5 border-indigo-500/30' : 'bg-slate-900 border-slate-800'">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl"
                     :class="metrics.pending_topups > 0 ? 'bg-indigo-500/10 text-indigo-400' : 'bg-slate-800 text-slate-400'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-white">Permohonan Top-Up Manual</h3>
                    <p class="text-xs text-slate-400">Bukti transfer pengguna menunggu verifikasi Finance</p>
                </div>
            </div>
            <div class="text-right">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold"
                      :class="metrics.pending_topups > 0 ? 'bg-indigo-500/20 text-indigo-300 animate-pulse' : 'bg-slate-800 text-slate-300'"
                      x-text="metrics.pending_topups">
                    {{ $metrics['pending_topups'] ?? 0 }}
                </span>
            </div>
        </div>
        <div class="mt-4 pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs">
            <span class="text-slate-400">Wajib konfirmasi sebelum saldo dikreditkan</span>
            <a href="{{ route('admin.finance.topups.index', ['status' => 'pending']) }}" class="text-indigo-400 hover:text-indigo-300 font-medium flex items-center gap-1">
                Verifikasi Bukti &rarr;
            </a>
        </div>
    </div>
</div>
