{{-- Custom Pricing Modal --}}
<div x-data="{
        open: false,
        productId: '',
        sellPrice: '',
        basePriceInfo: ''
     }"
     @open-pricing-modal.window="
        open = true;
        productId = $event.detail?.productId || '';
        sellPrice = $event.detail?.sellPrice || '';
        basePriceInfo = $event.detail?.basePriceInfo || '';
     "
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm transition-opacity" @click="open = false"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

        <div class="relative inline-block align-bottom bg-slate-900 border border-slate-800 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full p-6 text-white">
            <div class="flex items-center gap-3 mb-4">
                <div class="p-3 bg-amber-500/10 border border-amber-500/20 rounded-xl text-amber-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Atur Harga Khusus Produk</h3>
                    <p class="text-xs text-slate-400">Mitra: {{ $partner->name }}</p>
                </div>
            </div>

            <form action="{{ route('admin.partners.pricing.update', $partner) }}" method="POST" class="space-y-4">
                @csrf
                {{-- Product Select --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Pilih Produk (SKU)</label>
                    <select name="product_id" x-model="productId" required
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="">-- Pilih Produk / SKU --</option>
                        @foreach($availableProducts as $prod)
                            <option value="{{ $prod->id }}">
                                {{ $prod->name }} ({{ $prod->sku_code }}) - HPP: Rp {{ number_format($prod->base_price_cents->toCents() / 100, 0, ',', '.') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Sell Price --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Harga Jual Khusus Mitra (Rp)</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-sm">Rp</div>
                        <input type="number" name="sell_price" x-model="sellPrice" min="1" step="1" required placeholder="Contoh: 10500"
                               class="w-full bg-slate-950 border border-slate-800 rounded-xl pl-10 pr-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>
                    <p class="text-[11px] text-amber-400/90 mt-1 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Proteksi sistem: Harga tidak boleh di bawah HPP modal supplier.
                    </p>
                </div>

                <div class="flex items-center gap-2 pt-2">
                    <input type="checkbox" id="pricing_active" name="is_active" value="1" checked
                           class="rounded bg-slate-950 border-slate-800 text-indigo-600 focus:ring-indigo-500">
                    <label for="pricing_active" class="text-xs text-slate-300">Aktifkan harga khusus ini</label>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
                    <button type="button" @click="open = false"
                            class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium text-xs rounded-xl transition-colors">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl transition-all shadow-md shadow-indigo-600/30">
                        Simpan Harga Khusus
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
