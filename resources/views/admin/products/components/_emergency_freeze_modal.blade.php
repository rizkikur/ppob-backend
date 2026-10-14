{{-- MODAL EMERGENCY SKU FREEZE / BULK STATUS --}}
<div x-show="showFreezeModal" x-cloak
     class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div @click.away="showFreezeModal = false"
         class="bg-slate-900 border border-slate-800 rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-5">
        
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Emergency SKU Freeze & Status Massal</h3>
                    <p class="text-xs text-slate-400">Matikan atau aktifkan produk massal saat insiden gangguan upstream.</p>
                </div>
            </div>
            <button type="button" @click="showFreezeModal = false" class="text-slate-400 hover:text-white p-1">✕</button>
        </div>

        <form method="POST" action="{{ route('admin.products.bulk.status') }}" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div>
                    <label for="freeze_category_id" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        Target Kategori
                    </label>
                    <select id="freeze_category_id"
                            name="category_id"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-1 focus:ring-amber-500">
                        <option value="">Semua Kategori</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="freeze_provider_id" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        Target Provider
                    </label>
                    <select id="freeze_provider_id"
                            name="provider_id"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-1 focus:ring-amber-500">
                        <option value="">Semua Provider</option>
                        @foreach($providers as $prov)
                            <option value="{{ $prov->id }}">{{ $prov->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                    Tindakan Status <span class="text-rose-400">*</span>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-2.5 p-3 rounded-xl border border-slate-800 bg-slate-950 cursor-pointer hover:border-rose-500/40">
                        <input type="radio" name="is_active" value="0" checked class="text-rose-600 focus:ring-rose-500">
                        <div>
                            <div class="text-xs font-bold text-rose-400">Nonaktifkan (Freeze)</div>
                            <div class="text-[10px] text-slate-400">Blokir pembelian sementara</div>
                        </div>
                    </label>
                    <label class="flex items-center gap-2.5 p-3 rounded-xl border border-slate-800 bg-slate-950 cursor-pointer hover:border-emerald-500/40">
                        <input type="radio" name="is_active" value="1" class="text-emerald-600 focus:ring-emerald-500">
                        <div>
                            <div class="text-xs font-bold text-emerald-400">Aktifkan Kembali</div>
                            <div class="text-[10px] text-slate-400">Buka transaksi produk</div>
                        </div>
                    </label>
                </div>
            </div>

            <div>
                <label for="freeze_reason" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                    Alasan Operasional / Insiden <span class="text-rose-400">*</span>
                </label>
                <textarea id="freeze_reason"
                          name="reason"
                          rows="2"
                          required
                          minlength="5"
                          placeholder="Contoh: Gangguan upstream Digiflazz untuk seluruh denom Telkomsel..."
                          class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500/50"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-800">
                <button type="button" @click="showFreezeModal = false" class="px-4 py-2 rounded-xl text-xs font-medium text-slate-400 hover:text-white bg-slate-800">
                    Batal
                </button>
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-slate-950 font-bold text-xs shadow-lg shadow-amber-600/30 transition-all">
                    <span>Terapkan Status Massal</span>
                </button>
            </div>
        </form>
    </div>
</div>
