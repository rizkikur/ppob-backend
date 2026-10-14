{{-- Modal Manual Refund --}}
<div x-show="showRefundModal"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     aria-labelledby="modal-title"
     role="dialog"
     aria-modal="true">
    {{-- Backdrop --}}
    <div x-show="showRefundModal"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm transition-opacity"
         @click="showRefundModal = false"></div>

    <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
        <div x-show="showRefundModal"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="relative transform overflow-hidden rounded-2xl bg-slate-900 border border-slate-800 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg p-6">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 flex items-center justify-center w-12 h-12 rounded-xl bg-purple-500/10 text-purple-400 border border-purple-500/20">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-base font-bold text-white">Manual Refund ke Dompet Pengguna</h3>
                    <p class="text-xs text-slate-400 mt-1">
                        Dana sebesar <span class="text-emerald-400 font-bold font-mono">{{ $transaction->sell_price_cents ? $transaction->sell_price_cents->format() : 'Rp 0' }}</span> akan dikreditkan secara instan ke dompet pengguna <span class="text-slate-200 font-medium">{{ $transaction->user?->name }}</span>.
                    </p>
                </div>
            </div>

            <div class="mt-4 p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-xs text-amber-300 space-y-1">
                <div class="font-semibold flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-amber-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span>Perhatian Keselamatan Finansial:</span>
                </div>
                <p class="text-[11px] text-amber-300/90 leading-relaxed">
                    Operasi ini menambahkan kredit permanen ke buku besar dompet (append-only ledger). Aksi dicatat ke Audit Trail Admin dan memerlukan konfirmasi password sesi admin (berlaku 3 jam).
                </p>
            </div>

            <form method="POST" action="{{ route('admin.transactions.refund', $transaction) }}" class="mt-4 space-y-4" x-data="{ reason: '' }">
                @csrf
                <div>
                    <label for="refund_reason" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        Alasan Refund <span class="text-rose-400">*</span>
                    </label>
                    <textarea id="refund_reason"
                              name="reason"
                              x-model="reason"
                              rows="3"
                              required
                              placeholder="Cantumkan nomor tiket komplain CS atau alasan teknis kegagalan provider (minimal 10 karakter)..."
                              class="w-full bg-slate-950/80 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500 transition-all"></textarea>
                    <div class="flex items-center justify-between text-[11px] text-slate-500 mt-1">
                        <span>Minimal 10 karakter</span>
                        <span :class="reason.length < 10 ? 'text-amber-400' : 'text-emerald-400'" x-text="reason.length + ' / 255'"></span>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button"
                            @click="showRefundModal = false"
                            class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-colors">
                        Batal
                    </button>
                    <button type="submit"
                            :disabled="reason.trim().length < 10"
                            class="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-semibold shadow-lg shadow-purple-600/30 transition-all flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Konfirmasi & Eksekusi Refund</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
