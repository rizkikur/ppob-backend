{{-- Modal Status Override --}}
<div x-show="showOverrideModal"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     aria-labelledby="modal-title"
     role="dialog"
     aria-modal="true">
    {{-- Backdrop --}}
    <div x-show="showOverrideModal"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm transition-opacity"
         @click="showOverrideModal = false"></div>

    <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
        <div x-show="showOverrideModal"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="relative transform overflow-hidden rounded-2xl bg-slate-900 border border-slate-800 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg p-6">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 flex items-center justify-center w-12 h-12 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-base font-bold text-white">Manual Status Override</h3>
                    <p class="text-xs text-slate-400 mt-1">
                        Paksa status transaksi #{{ $transaction->id }} (Status saat ini: <span class="font-semibold text-amber-300">{{ strtoupper($transaction->status) }}</span>). Perubahan status dicatat di audit log.
                    </p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.transactions.override', $transaction) }}" class="mt-5 space-y-4" x-data="{ reason: '' }">
                @csrf
                <div>
                    <label for="override_status" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        Status Baru <span class="text-rose-400">*</span>
                    </label>
                    <select id="override_status"
                            name="status"
                            required
                            class="w-full bg-slate-950/80 border border-slate-700/80 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition-all">
                        <option value="success" @selected($transaction->status === 'success')>SUCCESS (Sukses)</option>
                        <option value="failed" @selected($transaction->status === 'failed')>FAILED (Gagal)</option>
                        <option value="processing" @selected($transaction->status === 'processing')>PROCESSING (Sedang Diproses)</option>
                        <option value="pending" @selected($transaction->status === 'pending')>PENDING (Menunggu)</option>
                    </select>
                </div>

                <div>
                    <label for="override_reason" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        Alasan Intervensi <span class="text-rose-400">*</span>
                    </label>
                    <textarea id="override_reason"
                              name="reason"
                              x-model="reason"
                              rows="3"
                              required
                              placeholder="Cantumkan alasan operasional mengapa status transaksi ini di-override (minimal 10 karakter)..."
                              class="w-full bg-slate-950/80 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition-all"></textarea>
                    <div class="flex items-center justify-between text-[11px] text-slate-500 mt-1">
                        <span>Minimal 10 karakter</span>
                        <span :class="reason.length < 10 ? 'text-amber-400' : 'text-emerald-400'" x-text="reason.length + ' / 255'"></span>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button"
                            @click="showOverrideModal = false"
                            class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-colors">
                        Batal
                    </button>
                    <button type="submit"
                            :disabled="reason.trim().length < 10"
                            class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-semibold shadow-lg shadow-amber-600/30 transition-all flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Simpan Perubahan Status</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
