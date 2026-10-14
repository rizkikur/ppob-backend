<x-admin.layouts.app>
    <div class="space-y-6 max-w-4xl mx-auto">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.products.index') }}"
               class="p-2 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700/60 text-slate-400 hover:text-white transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-white">Tambah Produk Baru</h1>
                <p class="text-xs text-slate-400 mt-1">Daftarkan SKU produk PPOB baru ke dalam katalog sistem.</p>
            </div>
        </div>

        @if($errors->any())
            <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm space-y-1">
                <div class="font-semibold flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Terdapat kesalahan pengisian data:</span>
                </div>
                <ul class="list-disc list-inside text-xs pl-2 space-y-0.5">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-slate-800/80 backdrop-blur-md rounded-2xl border border-slate-700/60 p-6 shadow-xl">
            <form method="POST" action="{{ route('admin.products.store') }}" class="space-y-5">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- SKU Code --}}
                    <div>
                        <label for="sku_code" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                            Kode SKU <span class="text-rose-400">*</span>
                        </label>
                        <input type="text"
                               id="sku_code"
                               name="sku_code"
                               value="{{ old('sku_code') }}"
                               placeholder="Contoh: TSEL10K, PLN20K"
                               required
                               class="w-full bg-slate-900/80 border border-slate-700/80 rounded-xl px-3.5 py-2 text-sm text-slate-100 placeholder-slate-500 uppercase font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                    </div>

                    {{-- Name --}}
                    <div>
                        <label for="name" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                            Nama Produk <span class="text-rose-400">*</span>
                        </label>
                        <input type="text"
                               id="name"
                               name="name"
                               value="{{ old('name') }}"
                               placeholder="Contoh: Telkomsel 10.000 Reguler"
                               required
                               class="w-full bg-slate-900/80 border border-slate-700/80 rounded-xl px-3.5 py-2 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                    </div>

                    {{-- Category --}}
                    <div>
                        <label for="category_id" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                            Kategori <span class="text-rose-400">*</span>
                        </label>
                        <select id="category_id"
                                name="category_id"
                                required
                                class="w-full bg-slate-900/80 border border-slate-700/80 rounded-xl px-3.5 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                            <option value="">Pilih Kategori...</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" @selected(old('category_id') == $cat->id)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Provider --}}
                    <div>
                        <label for="provider_id" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                            Provider Utama <span class="text-rose-400">*</span>
                        </label>
                        <select id="provider_id"
                                name="provider_id"
                                required
                                class="w-full bg-slate-900/80 border border-slate-700/80 rounded-xl px-3.5 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                            <option value="">Pilih Provider...</option>
                            @foreach($providers as $prov)
                                <option value="{{ $prov->id }}" @selected(old('provider_id') == $prov->id)>{{ $prov->name }} ({{ $prov->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Product Type --}}
                    <div>
                        <label for="product_type" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                            Tipe Produk <span class="text-rose-400">*</span>
                        </label>
                        <select id="product_type"
                                name="product_type"
                                required
                                class="w-full bg-slate-900/80 border border-slate-700/80 rounded-xl px-3.5 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                            <option value="prepaid" @selected(old('product_type') === 'prepaid')>PREPAID (Prabayar)</option>
                            <option value="postpaid" @selected(old('product_type') === 'postpaid')>POSTPAID (Pascabayar / Tagihan)</option>
                        </select>
                    </div>

                    {{-- Base Price --}}
                    <div>
                        <label for="base_price_rupiah" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                            Harga Modal Provider (Rp) <span class="text-rose-400">*</span>
                        </label>
                        <input type="number"
                               step="0.01"
                               id="base_price_rupiah"
                               name="base_price_rupiah"
                               value="{{ old('base_price_rupiah') }}"
                               placeholder="Contoh: 10100"
                               required
                               class="w-full bg-slate-900/80 border border-slate-700/80 rounded-xl px-3.5 py-2 text-sm text-slate-100 placeholder-slate-500 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                    </div>

                    {{-- Admin Fee --}}
                    <div>
                        <label for="admin_fee_rupiah" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                            Biaya Admin Default (Rp)
                        </label>
                        <input type="number"
                               step="0.01"
                               id="admin_fee_rupiah"
                               name="admin_fee_rupiah"
                               value="{{ old('admin_fee_rupiah', 0) }}"
                               placeholder="Contoh: 0 atau 2500"
                               class="w-full bg-slate-900/80 border border-slate-700/80 rounded-xl px-3.5 py-2 text-sm text-slate-100 placeholder-slate-500 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                    </div>

                    {{-- Active Toggle --}}
                    <div class="flex items-center pt-6">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" class="sr-only peer" @checked(old('is_active', true))>
                            <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                            <span class="ml-3 text-xs font-semibold text-slate-200">Aktifkan Produk Segera</span>
                        </label>
                    </div>
                </div>

                {{-- Description --}}
                <div>
                    <label for="description" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        Keterangan Tambahan / Format Tujuan
                    </label>
                    <textarea id="description"
                              name="description"
                              rows="2"
                              placeholder="Keterangan singkat produk..."
                              class="w-full bg-slate-900/80 border border-slate-700/80 rounded-xl px-3.5 py-2 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">{{ old('description') }}</textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-700/60">
                    <a href="{{ route('admin.products.index') }}"
                       class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-colors">
                        Batal
                    </a>
                    <button type="submit"
                            class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition-all">
                        Simpan Produk
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-admin.layouts.app>
