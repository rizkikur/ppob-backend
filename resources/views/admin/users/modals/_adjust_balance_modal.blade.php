<div x-data="{ show: false, type: 'credit' }"
     @open-balance-modal.window="show = true; type = $event.detail.type || 'credit'"
     x-show="show"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     role="dialog"
     aria-modal="true">
    <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="show = false"></div>

    <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative w-full max-w-md rounded-2xl bg-slate-900 border border-slate-800 p-6 shadow-2xl space-y-4">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Penyesuaian Saldo Pengguna</span>
            </h3>

            <form action="{{ route('admin.users.adjust_balance', $user) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Jenis Penyesuaian <span class="text-rose-400">*</span></label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-800 bg-slate-950 cursor-pointer">
                            <input type="radio" name="type" value="credit" x-model="type" class="text-indigo-600 focus:ring-indigo-500">
                            <span class="text-xs font-semibold text-emerald-400">+ Tambah Saldo (Credit)</span>
                        </label>
                        <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-800 bg-slate-950 cursor-pointer">
                            <input type="radio" name="type" value="debit" x-model="type" class="text-indigo-600 focus:ring-indigo-500">
                            <span class="text-xs font-semibold text-rose-400">- Potong Saldo (Debit)</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label for="amount" class="block text-xs font-semibold text-slate-300 mb-1">Nominal (Rupiah) <span class="text-rose-400">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs text-slate-500">Rp</span>
                        <input type="number" id="amount" name="amount" min="1" step="any" required placeholder="50.000"
                               class="w-full bg-slate-950 border border-slate-800 rounded-xl pl-9 pr-3 py-2 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                    </div>
                </div>

                <div>
                    <label for="note" class="block text-xs font-semibold text-slate-300 mb-1">Catatan / Alasan Penyesuaian <span class="text-rose-400">*</span></label>
                    <textarea id="note" name="note" rows="2" required placeholder="Misal: Koreksi transaksi gagal #TRX-123 atau bonus referral"
                              class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500"></textarea>
                    <p class="text-[10px] text-slate-500 mt-1">Alasan wajib dicatat demi kepatuhan audit trail.</p>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-800">
                    <button type="button" @click="show = false" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold">Batal</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg shadow-indigo-600/30">Proses Penyesuaian</button>
                </div>
            </form>
        </div>
    </div>
</div>
