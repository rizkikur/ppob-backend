{{-- Modal Requery Provider --}}
<div x-show="showRequeryModal"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     aria-labelledby="modal-title"
     role="dialog"
     aria-modal="true">
    {{-- Backdrop --}}
    <div x-show="showRequeryModal"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm transition-opacity"
         @click="showRequeryModal = false"></div>

    <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
        <div x-show="showRequeryModal"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="relative transform overflow-hidden rounded-2xl bg-slate-900 border border-slate-800 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg p-6">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 flex items-center justify-center w-12 h-12 rounded-xl bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-base font-bold text-white">Re-query Status ke Provider Upstream</h3>
                    <p class="text-xs text-slate-400 mt-1">
                        Sistem akan memanggil API provider upstream (<span class="text-slate-200 font-semibold">{{ $transaction->product?->provider?->name ?? 'Default Provider' }}</span>) untuk menanyakan status eksekusi transaksi #{{ $transaction->id }}.
                    </p>
                    <div class="mt-3 p-3 rounded-xl bg-slate-950/60 border border-slate-800 text-xs text-slate-300 space-y-1 font-mono">
                        <div>Ref: {{ $transaction->provider_ref ?? 'ID #'.$transaction->id }}</div>
                        <div>Tujuan: {{ $transaction->customer_number }}</div>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.transactions.requery', $transaction) }}" class="mt-6 flex items-center justify-end gap-3">
                @csrf
                <button type="button"
                        @click="showRequeryModal = false"
                        class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-colors">
                    Batal
                </button>
                <button type="submit"
                        class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>Lakukan Re-query</span>
                </button>
            </form>
        </div>
    </div>
</div>
