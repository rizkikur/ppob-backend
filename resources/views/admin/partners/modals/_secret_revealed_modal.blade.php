{{-- One-Time Secret Display Modal (Stripe / AWS Pattern) --}}
@if(session('newly_generated_secret'))
<div x-data="{ open: true, copied: false }"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-md transition-opacity"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="relative inline-block align-bottom bg-slate-900 border-2 border-amber-500/50 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full p-6 text-white animate-in fade-in zoom-in-95 duration-200">
            {{-- Header Badge --}}
            <div class="flex items-center gap-3 mb-4">
                <div class="p-3 bg-amber-500/10 border border-amber-500/30 rounded-xl text-amber-400">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-white" id="modal-title">Kunci Rahasia API Baru (API Secret)</h3>
                    <p class="text-xs text-amber-400 font-medium">Mitra: {{ session('newly_generated_name') ?? 'Mitra B2B' }}</p>
                </div>
            </div>

            {{-- Warning Banner --}}
            <div class="p-3.5 bg-rose-500/10 border border-rose-500/30 rounded-xl mb-4 text-xs text-rose-300 leading-relaxed">
                <span class="font-bold text-rose-200 uppercase tracking-wider">Perhatian Kritis:</span>
                Kunci rahasia (Secret Key) ini <strong>HANYA DITAMPILKAN SATU KALI SAJA</strong>. Simpan sekarang di tempat penyimpanan aman klien Anda. Setelah jendela modal ini ditutup, sistem tidak akan pernah bisa menampilkan kunci ini kembali!
            </div>

            {{-- Public API Key Info --}}
            @if(session('newly_generated_key'))
            <div class="mb-4">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Public API Key (Identifier)</label>
                <div class="flex items-center gap-2 bg-slate-950/80 border border-slate-800 rounded-xl px-3.5 py-2.5 font-mono text-xs text-indigo-300 select-all">
                    <span>{{ session('newly_generated_key') }}</span>
                </div>
            </div>
            @endif

            {{-- Secret Key Field with Copy Button --}}
            <div class="mb-6">
                <label class="block text-xs font-semibold uppercase tracking-wider text-amber-400 mb-1.5 flex items-center justify-between">
                    <span>Secret Key (HMAC Signature)</span>
                    <span x-show="copied" x-transition class="text-emerald-400 text-xs font-normal">Tersalin ke clipboard!</span>
                </label>
                <div class="relative flex items-center bg-slate-950 border border-amber-500/40 rounded-xl p-3 font-mono text-sm text-amber-200 break-all select-all">
                    <span id="raw-secret-key" class="pr-20">{{ session('newly_generated_secret') }}</span>
                    <button type="button"
                            @click="navigator.clipboard.writeText('{{ session('newly_generated_secret') }}'); copied = true; setTimeout(() => copied = false, 2500)"
                            class="absolute right-2 px-3 py-1.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-lg transition-all flex items-center gap-1.5 shadow-md">
                        <svg x-show="!copied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                        <svg x-show="copied" class="w-4 h-4 text-slate-950" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span x-text="copied ? 'Tersalin' : 'Salin'">Salin</span>
                    </button>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button"
                        @click="open = false"
                        class="w-full sm:w-auto px-5 py-2.5 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold text-sm rounded-xl transition-all shadow-lg shadow-amber-500/20">
                    Saya Sudah Menyimpan Secret Ini Dengan Aman
                </button>
            </div>
        </div>
    </div>
</div>
@endif
