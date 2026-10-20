<x-admin.layouts.app>
    <div class="space-y-6 max-w-5xl mx-auto" x-data="{ showDeleteModal: false }">
        {{-- Flash Alerts --}}
        @if(session('success'))
            <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>{{ session('success') }}</span>
                </div>
                <button type="button" @click="$el.parentElement.remove()" class="text-emerald-400/70 hover:text-emerald-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @endif

        @if($errors->any())
            <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm space-y-1">
                <div class="font-semibold flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Terdapat kendala validasi data:</span>
                </div>
                <ul class="list-disc list-inside text-xs pl-2 space-y-0.5">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Top Navigation Bar --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.products.index') }}"
                   class="p-2 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700/60 text-slate-400 hover:text-white transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-bold tracking-tight text-white">{{ $product->name }}</h1>
                        <span class="font-mono text-xs px-2.5 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-bold">
                            {{ $product->sku_code }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Provider Utama: {{ $product->provider?->name ?? '-' }} | Kategori: {{ $product->category?->name ?? '-' }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button type="button"
                        @click="showDeleteModal = true"
                        class="px-3.5 py-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 text-xs font-semibold transition-all">
                    Hapus Produk
                </button>
            </div>
        </div>

        {{-- Basic Information Form --}}
        <div class="bg-slate-800/80 backdrop-blur-md rounded-2xl border border-slate-700/60 p-5 sm:p-6 shadow-xl space-y-5">
            <h2 class="text-base font-bold text-white flex items-center gap-2 border-b border-slate-700/60 pb-3">
                <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                <span>Informasi Dasar & SKU Produk</span>
            </h2>

            <form method="POST" action="{{ route('admin.products.update', $product) }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    {{-- SKU --}}
                    <div>
                        <label for="sku_code" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                            Kode SKU <span class="text-rose-400">*</span>
                        </label>
                        <input type="text"
                               id="sku_code"
                               name="sku_code"
                               value="{{ old('sku_code', $product->sku_code) }}"
                               required
                               class="w-full bg-slate-900/80 border border-slate-700/80 rounded-xl px-3.5 py-2 text-sm text-slate-100 uppercase font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500">
                    </div>

                    {{-- Name --}}
                    <div>
                        <label for="name" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                            Nama Produk <span class="text-rose-400">*</span>
                        </label>
                        <input type="text"
                               id="name"
                               name="name"
                               value="{{ old('name', $product->name) }}"
                               required
                               class="w-full bg-slate-900/80 border border-slate-700/80 rounded-xl px-3.5 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500">
                    </div>

                    {{-- Category --}}
                    <div>
                        <label for="category_id" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                            Kategori <span class="text-rose-400">*</span>
                        </label>
                        <select id="category_id"
                                name="category_id"
                                required
                                class="w-full bg-slate-900/80 border border-slate-700/80 rounded-xl px-3.5 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500">
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" @selected(old('category_id', $product->category_id) == $cat->id)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Operator / Brand --}}
                    <div>
                        <label for="operator_id" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                            Operator / Brand
                        </label>
                        <select id="operator_id"
                                name="operator_id"
                                class="w-full bg-slate-900/80 border border-slate-700/80 rounded-xl px-3.5 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500">
                            <option value="">-- Pilih Operator / Brand (Opsional) --</option>
                            @foreach($operators as $op)
                                <option value="{{ $op->id }}" @selected(old('operator_id', $product->operator_id) == $op->id)>{{ $op->name }} ({{ $op->code }})</option>
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
                                class="w-full bg-slate-900/80 border border-slate-700/80 rounded-xl px-3.5 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500">
                            @foreach($providers as $prov)
                                <option value="{{ $prov->id }}" @selected(old('provider_id', $product->provider_id) == $prov->id)>{{ $prov->name }} ({{ $prov->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Type --}}
                    <div>
                        <label for="product_type" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                            Tipe Produk <span class="text-rose-400">*</span>
                        </label>
                        <select id="product_type"
                                name="product_type"
                                required
                                class="w-full bg-slate-900/80 border border-slate-700/80 rounded-xl px-3.5 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500">
                            <option value="prepaid" @selected(old('product_type', $product->product_type) === 'prepaid')>PREPAID (Prabayar)</option>
                            <option value="postpaid" @selected(old('product_type', $product->product_type) === 'postpaid')>POSTPAID (Pascabayar)</option>
                        </select>
                    </div>

                    {{-- Base Price --}}
                    <div>
                        <label for="base_price_rupiah" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                            Modal Provider (Rp) <span class="text-rose-400">*</span>
                        </label>
                        <input type="number"
                               step="0.01"
                               id="base_price_rupiah"
                               name="base_price_rupiah"
                               value="{{ old('base_price_rupiah', $product->base_price_cents ? $product->base_price_cents->toRupiah() : 0) }}"
                               required
                               class="w-full bg-slate-900/80 border border-slate-700/80 rounded-xl px-3.5 py-2 text-sm text-slate-100 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500">
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-3 border-t border-slate-700/60">
                    <div class="flex flex-wrap items-center gap-6">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" class="sr-only peer" @checked(old('is_active', $product->is_active))>
                            <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                            <span class="ml-3 text-xs font-semibold text-slate-200">Status Produk Aktif</span>
                        </label>

                        <label class="relative inline-flex items-center cursor-pointer" title="Jika dinonaktifkan, transaksi langsung ditolak jika provider utama gangguan dan tidak dialihkan ke supplier backup">
                            <input type="checkbox" name="allow_failover" value="1" class="sr-only peer" @checked(old('allow_failover', $product->allow_failover))>
                            <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            <span class="ml-3 text-xs font-semibold text-slate-200">Izinkan Failover Supplier</span>
                        </label>
                    </div>

                    <button type="submit"
                            class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition-all">
                        Perbarui Info Dasar
                    </button>
                </div>
            </form>
        </div>

        {{-- Matriks Tiering Harga --}}
        @include('admin.products.components._price_tier_matrix')

        {{-- Pemetaan Routing Provider Failover --}}
        @include('admin.products.components._sku_mapping_table')

        {{-- Modal Delete Confirmation --}}
        <div x-show="showDeleteModal"
             x-cloak
             class="fixed inset-0 z-50 overflow-y-auto"
             role="dialog"
             aria-modal="true">
            <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="showDeleteModal = false"></div>
            <div class="flex min-h-full items-center justify-center p-4 text-center">
                <div class="relative transform overflow-hidden rounded-2xl bg-slate-900 border border-slate-800 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-md p-6">
                    <h3 class="text-base font-bold text-white">Konfirmasi Hapus Produk</h3>
                    <p class="text-xs text-slate-400 mt-2">
                        Apakah Anda yakin ingin menghapus produk <span class="font-bold text-white">{{ $product->name }} ({{ $product->sku_code }})</span>? Tindakan ini akan dicatat di log audit.
                    </p>
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" @click="showDeleteModal = false" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-semibold">Batal</button>
                        <form method="POST" action="{{ route('admin.products.destroy', $product) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-semibold shadow-lg shadow-rose-600/30">Hapus Produk</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin.layouts.app>
