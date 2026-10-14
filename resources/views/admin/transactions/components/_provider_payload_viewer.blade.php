<div x-data="{ copied: false, open: false }" class="bg-slate-800/80 backdrop-blur-md rounded-2xl border border-slate-700/60 p-5 shadow-xl space-y-4">
    <div class="flex items-center justify-between border-b border-slate-700/60 pb-3">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
            <h2 class="text-sm font-bold text-white uppercase tracking-wider">Payload Provider Upstream</h2>
        </div>
        @if($maskedProviderResponse)
            <div class="flex items-center gap-2">
                <button type="button"
                        @click="navigator.clipboard.writeText(JSON.stringify({{ json_encode($maskedProviderResponse) }}, null, 2)); copied = true; setTimeout(() => copied = false, 2000)"
                        class="text-xs text-indigo-400 hover:text-indigo-300 transition-colors flex items-center gap-1 font-mono">
                    <span x-text="copied ? 'Tersalin!' : 'Salin JSON'">Salin JSON</span>
                </button>
            </div>
        @endif
    </div>

    @if($maskedProviderResponse)
        <div>
            <div class="text-xs text-slate-400 mb-2">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20 text-[11px]">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    Kredensial Sensitif Dimaskir
                </span>
            </div>
            <pre class="bg-slate-950/80 border border-slate-800 rounded-xl p-4 text-xs font-mono text-emerald-400 overflow-x-auto max-h-80 leading-relaxed">{{ json_encode($maskedProviderResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    @else
        <div class="py-6 text-center text-slate-500 text-xs">
            <svg class="w-8 h-8 text-slate-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Belum ada data respons tersimpan dari provider untuk transaksi ini.
        </div>
    @endif
</div>
