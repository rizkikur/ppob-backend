<x-admin.layouts.app>
    <div class="space-y-6" x-data="{ showSyncModal: false, showFreezeModal: false, showBulkPriceModal: false }">
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

        @if(session('error'))
            <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span>{{ session('error') }}</span>
                </div>
                <button type="button" @click="$el.parentElement.remove()" class="text-rose-400/70 hover:text-rose-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @endif

        {{-- Header Bar --}}
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-white">Katalog Produk & SKU</h1>
                <p class="mt-1 text-sm text-slate-400">Kelola master produk PPOB, harga modal provider, dan matriks tier harga pengguna.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                {{-- Auto-Sync Pricelist --}}
                <button type="button"
                        @click="showSyncModal = true"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-blue-600/20 hover:bg-blue-600/30 text-blue-300 border border-blue-500/30 text-xs font-semibold transition-all">
                    <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>Sync Provider</span>
                </button>

                {{-- Emergency Freeze --}}
                <button type="button"
                        @click="showFreezeModal = true"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-amber-600/20 hover:bg-amber-600/30 text-amber-300 border border-amber-500/30 text-xs font-semibold transition-all">
                    <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span>Emergency Freeze</span>
                </button>

                {{-- Bulk Price Adjustment --}}
                <button type="button"
                        @click="showBulkPriceModal = true"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-purple-600/20 hover:bg-purple-600/30 text-purple-300 border border-purple-500/30 text-xs font-semibold transition-all">
                    <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Ubah Harga Massal</span>
                </button>

                {{-- Manage Categories --}}
                <a href="{{ route('admin.categories.index') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700/60 text-slate-300 hover:text-white text-xs font-semibold transition-all">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    <span>Kategori</span>
                </a>

                {{-- Create Product --}}
                <a href="{{ route('admin.products.create') }}"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Tambah Produk</span>
                </a>
            </div>
        </div>

        {{-- Filter Bar --}}
        <div class="bg-slate-800/80 backdrop-blur-md rounded-2xl border border-slate-700/60 p-4 sm:p-5 shadow-xl">
            <form method="GET" action="{{ route('admin.products.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
                {{-- Search --}}
                <div class="lg:col-span-2">
                    <label for="q" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Pencarian</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </span>
                        <input type="text"
                               id="q"
                               name="q"
                               value="{{ request('q', $filters['q'] ?? '') }}"
                               placeholder="Cari SKU atau nama produk..."
                               class="w-full bg-slate-900/80 border border-slate-700/80 rounded-xl pl-9 pr-3.5 py-2 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                    </div>
                </div>

                {{-- Category Filter --}}
                <div>
                    <label for="category_id" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Kategori</label>
                    <select id="category_id"
                            name="category_id"
                            class="w-full bg-slate-900/80 border border-slate-700/80 rounded-xl px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                        <option value="">Semua Kategori</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" @selected((string)request('category_id', $filters['category_id'] ?? '') === (string)$cat->id)>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Provider Filter --}}
                <div>
                    <label for="provider_id" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Provider</label>
                    <select id="provider_id"
                            name="provider_id"
                            class="w-full bg-slate-900/80 border border-slate-700/80 rounded-xl px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                        <option value="">Semua Provider</option>
                        @foreach($providers as $prov)
                            <option value="{{ $prov->id }}" @selected((string)request('provider_id', $filters['provider_id'] ?? '') === (string)$prov->id)>
                                {{ $prov->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Action & Submit --}}
                <div class="flex items-end gap-2">
                    <button type="submit"
                            class="w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl shadow-lg shadow-indigo-600/30 transition-all">
                        Terapkan
                    </button>
                    @if(request()->hasAny(['q', 'category_id', 'provider_id', 'is_active', 'product_type']))
                        <a href="{{ route('admin.products.index') }}"
                           class="px-3 py-2 text-xs font-semibold text-slate-400 hover:text-white bg-slate-800 hover:bg-slate-700 rounded-xl transition-all"
                           title="Reset Filter">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Products Table --}}
        <div class="bg-slate-800/80 backdrop-blur-md rounded-2xl border border-slate-700/60 shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-700/60 bg-slate-900/40 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                            <th class="py-3.5 px-4">SKU & Nama Produk</th>
                            <th class="py-3.5 px-4">Kategori</th>
                            <th class="py-3.5 px-4">Provider Utama</th>
                            <th class="py-3.5 px-4 text-right">Modal Provider</th>
                            <th class="py-3.5 px-4 text-center">Tipe</th>
                            <th class="py-3.5 px-4 text-center">Status</th>
                            <th class="py-3.5 px-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40 text-sm">
                        @forelse($products as $prod)
                            <tr class="hover:bg-slate-700/30 transition-colors">
                                <td class="py-3.5 px-4">
                                    <div class="font-mono text-xs font-bold text-indigo-400">{{ $prod->sku_code }}</div>
                                    <div class="font-medium text-slate-200 text-xs">{{ $prod->name }}</div>
                                </td>
                                <td class="py-3.5 px-4 text-xs text-slate-300">
                                    {{ $prod->category?->name ?? '-' }}
                                </td>
                                <td class="py-3.5 px-4 text-xs text-slate-300">
                                    {{ $prod->provider?->name ?? '-' }}
                                </td>
                                <td class="py-3.5 px-4 text-right whitespace-nowrap font-mono text-xs font-semibold text-slate-200">
                                    {{ $prod->base_price_cents ? $prod->base_price_cents->format() : 'Rp 0,00' }}
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono uppercase {{ $prod->product_type === 'prepaid' ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' : 'bg-purple-500/10 text-purple-400 border border-purple-500/20' }}">
                                        {{ $prod->product_type }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap" x-data="{ active: {{ $prod->is_active ? 'true' : 'false' }}, loading: false }">
                                    <button type="button"
                                            @click="loading = true; fetch('{{ route('admin.products.toggle', $prod) }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } }).then(r => r.json()).then(d => { if(d.success) active = d.is_active; }).finally(() => loading = false)"
                                            :disabled="loading"
                                            :class="active ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30 hover:bg-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border-rose-500/30 hover:bg-rose-500/20'"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border transition-all cursor-pointer"
                                            title="Klik untuk ubah status secara instan">
                                        <span class="w-1.5 h-1.5 rounded-full" :class="active ? 'bg-emerald-400 animate-pulse' : 'bg-rose-400'"></span>
                                        <span x-text="loading ? '...' : (active ? 'Aktif' : 'Nonaktif')"></span>
                                    </button>
                                </td>
                                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.products.edit', $prod) }}"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-700/60 hover:bg-indigo-600 text-slate-200 hover:text-white rounded-xl text-xs font-semibold transition-all">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            <span>Kelola</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-500 text-xs">
                                    Tidak ada produk yang cocok dengan pencarian.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($products->hasPages())
                <div class="p-4 border-t border-slate-700/60 bg-slate-900/30">
                    {{ $products->links() }}
                </div>
            @endif
        </div>

        {{-- Modals --}}
        @include('admin.products.components._sync_pricelist_modal')
        @include('admin.products.components._emergency_freeze_modal')
        @include('admin.products.components._bulk_price_modal')
    </div>
</x-admin.layouts.app>
