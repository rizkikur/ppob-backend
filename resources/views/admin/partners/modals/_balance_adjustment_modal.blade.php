{{-- Balance Adjustment Modal --}}
<div x-data="{ open: false, type: 'credit', amount: '' }"
     @open-balance-modal.window="open = true; type = $event.detail?.type || 'credit'"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm transition-opacity" @click="open = false"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

        <div class="relative inline-block align-bottom bg-slate-900 border border-slate-800 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full p-6 text-white">
            <div class="flex items-center gap-3 mb-4">
                <div class="p-3 bg-indigo-500/10 border border-indigo-500/20 rounded-xl text-indigo-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Penyesuaian Saldo Mitra</h3>
                    <p class="text-xs text-slate-400">Mitra: {{ $partner->name }}</p>
                </div>
            </div>

            <form action="{{ route('admin.partners.adjust_balance', $partner) }}" method="POST" class="space-y-4">
                @csrf
                {{-- Type Selection Tabs --}}
                <div class="grid grid-cols-2 gap-2 bg-slate-950 p-1.5 rounded-xl border border-slate-800">
                    <button type="button" @click="type = 'credit'"
                            :class="type === 'credit' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'text-slate-400 hover:text-white'"
                            class="py-2 text-xs font-semibold rounded-lg transition-all flex items-center justify-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                        <span>Tambah (Credit)</span>
                    </button>
                    <button type="button" @click="type = 'debit'"
                            :class="type === 'debit' ? 'bg-rose-500/20 text-rose-300 border border-rose-500/40' : 'text-slate-400 hover:text-white'"
                            class="py-2 text-xs font-semibold rounded-lg transition-all flex items-center justify-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                        <span>Potong (Debit)</span>
                    </button>
                </div>
                <input type="hidden" name="type" :value="type">

                {{-- Amount --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Nominal Penyesuaian (Rp)</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-sm">Rp</div>
                        <input type="number" name="amount" x-model="amount" min="1" step="1" required placeholder="Contoh: 1000000"
                               class="w-full bg-slate-950 border border-slate-800 rounded-xl pl-10 pr-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>
                </div>

                {{-- Note --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Alasan / Catatan Audit <span class="text-rose-400">*</span></label>
                    <textarea name="note" rows="2" required placeholder="Contoh: Topup manual via transfer BCA rek 123 / Koreksi saldo selisih invoice"
                              class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
                    <button type="button" @click="open = false"
                            class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium text-xs rounded-xl transition-colors">
                        Batal
                    </button>
                    <button type="submit"
                            :class="type === 'credit' ? 'bg-emerald-600 hover:bg-emerald-500 shadow-emerald-600/30' : 'bg-rose-600 hover:bg-rose-500 shadow-rose-600/30'"
                            class="px-4 py-2 text-white font-bold text-xs rounded-xl transition-all shadow-md">
                        Eksekusi Penyesuaian
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
