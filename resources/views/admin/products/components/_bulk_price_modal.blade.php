{{-- MODAL BULK PRICE & MARGIN ADJUSTMENT --}}
<div x-show="showBulkPriceModal" x-cloak
     class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div @click.away="showBulkPriceModal = false"
         class="bg-slate-900 border border-slate-800 rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-5">
        
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Penyesuaian Harga & Margin Massal</h3>
                    <p class="text-xs text-slate-400">Ubah harga jual tiering secara serentak per kategori atau provider.</p>
                </div>
            </div>
            <button type="button" @click="showBulkPriceModal = false" class="text-slate-400 hover:text-white p-1">✕</button>
        </div>

        <form method="POST" action="{{ route('admin.products.bulk.prices') }}" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div>
                    <label for="bulk_category_id" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        Kategori Target
                    </label>
                    <select id="bulk_category_id"
                            name="category_id"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-1 focus:ring-purple-500">
                        <option value="">Semua Kategori</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="bulk_provider_id" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        Provider Target
                    </label>
                    <select id="bulk_provider_id"
                            name="provider_id"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-1 focus:ring-purple-500">
                        <option value="">Semua Provider</option>
                        @foreach($providers as $prov)
                            <option value="{{ $prov->id }}">{{ $prov->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        Arah <span class="text-rose-400">*</span>
                    </label>
                    <select name="direction" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-100">
                        <option value="increase">Naikkan (+)</option>
                        <option value="decrease">Turunkan (-)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        Tipe Rumus <span class="text-rose-400">*</span>
                    </label>
                    <select name="adjustment_type" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-100">
                        <option value="fixed">Nominal Flat (Rp)</option>
                        <option value="percentage">Persentase (%)</option>
                    </select>
                </div>

                <div>
                    <label for="bulk_amount" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        Nilai <span class="text-rose-400">*</span>
                    </label>
                    <input type="number"
                           id="bulk_amount"
                           name="amount"
                           step="any"
                           min="0.01"
                           placeholder="Contoh: 150 atau 2.5"
                           required
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-100 font-mono">
                </div>
            </div>

            <div class="p-3.5 rounded-xl bg-purple-950/30 border border-purple-500/30 text-xs text-purple-300 flex items-start gap-2">
                <svg class="w-4 h-4 text-purple-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Proteksi Anti-Rugi Aktif: Sistem secara otomatis membatalkan penurunan harga jika harga jual baru berada di bawah HPP modal provider.</span>
            </div>

            <div>
                <label for="bulk_reason" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                    Alasan Penyesuaian <span class="text-rose-400">*</span>
                </label>
                <textarea id="bulk_reason"
                          name="reason"
                          rows="2"
                          required
                          minlength="5"
                          placeholder="Contoh: Penyesuaian margin operasional kuartal 4..."
                          class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-purple-500/50"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-800">
                <button type="button" @click="showBulkPriceModal = false" class="px-4 py-2 rounded-xl text-xs font-medium text-slate-400 hover:text-white bg-slate-800">
                    Batal
                </button>
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-semibold text-xs shadow-lg shadow-purple-600/30 transition-all">
                    <span>Terapkan Harga Massal</span>
                </button>
            </div>
        </form>
    </div>
</div>
