{{-- API Credential & Protocol Card (Stripe Pattern) --}}
<div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-slate-800">
        <div>
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                <span>Kredensial API & Otentikasi H2H</span>
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">Kunci akses Host-to-Host standar industri dengan penandatanganan HMAC SHA-256</p>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $partner->is_active ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' }}">
                <span class="w-1.5 h-1.5 rounded-full {{ $partner->is_active ? 'bg-emerald-400' : 'bg-rose-400' }} mr-1.5"></span>
                {{ $partner->is_active ? 'API Aktif' : 'Akses Ditangguhkan' }}
            </span>
        </div>
    </div>

    <div class="space-y-4">
        {{-- Public API Key --}}
        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label class="text-xs font-semibold uppercase tracking-wider text-slate-400">Public API Key (Header: X-Api-Key)</label>
                <button type="button"
                        @click="$dispatch('open-rotate-modal', { type: 'key' })"
                        class="text-xs font-medium text-slate-400 hover:text-amber-400 transition-colors flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>Rotate Key</span>
                </button>
            </div>
            <div x-data="{ copied: false }" class="flex items-center gap-2">
                <div class="flex-1 bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 font-mono text-xs text-indigo-300 select-all overflow-x-auto">
                    {{ $partner->api_key }}
                </div>
                <button type="button"
                        @click="navigator.clipboard.writeText('{{ $partner->api_key }}'); copied = true; setTimeout(() => copied = false, 2000)"
                        class="px-3 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium rounded-xl transition-all flex items-center gap-1.5">
                    <svg x-show="!copied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                    <svg x-show="copied" class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span x-text="copied ? 'Tersalin' : 'Salin'">Salin</span>
                </button>
            </div>
        </div>

        {{-- API Secret (Masked) --}}
        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label class="text-xs font-semibold uppercase tracking-wider text-slate-400">API Secret Key (HMAC Signature)</label>
                <button type="button"
                        @click="$dispatch('open-rotate-modal', { type: 'secret' })"
                        class="text-xs font-medium text-amber-400 hover:text-amber-300 transition-colors flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>Rotate Secret Baru</span>
                </button>
            </div>
            <div class="flex items-center gap-2">
                <div class="flex-1 bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 font-mono text-xs text-slate-500 tracking-widest flex items-center justify-between">
                    <span>sk_live_••••••••••••••••••••••••••••••••••••••••</span>
                    <span class="text-[11px] text-slate-500 uppercase tracking-normal">Terenkripsi</span>
                </div>
            </div>
            <p class="text-[11px] text-slate-500 mt-1">
                Kunci rahasia tidak dapat dilihat kembali demi standar keamanan. Jika hilang, klik <strong>Rotate Secret Baru</strong>.
            </p>
        </div>

        {{-- Protocol Settings & Rate Limit --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-3 border-t border-slate-800/80">
            <div class="bg-slate-950/60 border border-slate-800/60 rounded-xl p-3">
                <div class="text-[11px] text-slate-400 uppercase tracking-wider">Protokol</div>
                <div class="text-sm font-bold text-white uppercase mt-0.5">{{ $partner->protocol }}</div>
            </div>
            <div class="bg-slate-950/60 border border-slate-800/60 rounded-xl p-3">
                <div class="text-[11px] text-slate-400 uppercase tracking-wider">Mode Respon</div>
                <div class="text-sm font-bold text-indigo-400 uppercase mt-0.5">{{ $partner->response_mode }}</div>
            </div>
            <div class="bg-slate-950/60 border border-slate-800/60 rounded-xl p-3">
                <div class="text-[11px] text-slate-400 uppercase tracking-wider">Rate Limit</div>
                <div class="text-sm font-bold text-white mt-0.5">{{ $partner->rate_limit_rpm }} <span class="text-xs text-slate-400 font-normal">req/mnt</span></div>
            </div>
            <div class="bg-slate-950/60 border border-slate-800/60 rounded-xl p-3">
                <div class="text-[11px] text-slate-400 uppercase tracking-wider">Timeout Max</div>
                <div class="text-sm font-bold text-white mt-0.5">{{ $partner->response_timeout_ms }} <span class="text-xs text-slate-400 font-normal">ms</span></div>
            </div>
        </div>

        {{-- Webhook Callback URL --}}
        <div class="pt-3 border-t border-slate-800/80">
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Webhook Callback URL</label>
            <div class="flex items-center gap-2">
                <div class="flex-1 bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-xs font-mono text-slate-300 truncate">
                    {{ $partner->callback_url ?: 'Belum dikonfigurasi (Callback dinonaktifkan)' }}
                </div>
            </div>
        </div>
    </div>
</div>
