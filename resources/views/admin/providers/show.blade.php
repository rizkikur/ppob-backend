<x-admin.layouts.app>
    
    <script nonce="{{ $csp_nonce ?? Vite::cspNonce() }}">
    function providerDetailPage() {
        return {
            activeTab: @json(request('tab', 'credentials')),
            showResetModal: false,
            showToggleModal: false,
            showAddErrorModal: false,
            showEditErrorModal: false,
            editingError: { id: null, upstream_code: '', internal_code: 'ERR_GENERAL', custom_message: '', is_retryable: false },
            toggleReason: '',
            copied: false,
            copyCallbackUrl(url) {
                navigator.clipboard.writeText(url);
                this.copied = true;
                setTimeout(() => this.copied = false, 2500);
            },

            // Products Tab State
            products: Object.values(@json($formattedProducts ?? [])),
            searchProduct: '',
            filterCategory: '',
            sortProduct: 'name_asc',
            filterStatus: '',
            showAddProductModal: false,
            showEditProductModal: false,
            editingProduct: {
                id: null,
                supplier_sku: '',
                name: '',
                category_name: '',
                cost: 0,
                status: 'active',
                inquiry_url: '',
                payment_url: '',
                update_url: ''
            },

            openEditProduct(p) {
                this.editingProduct = {
                    id: p.id,
                    supplier_sku: p.supplier_sku,
                    name: p.name,
                    category_name: p.category_name || '',
                    cost: Math.round((p.cost_cents || 0) / 100),
                    status: p.status,
                    inquiry_url: p.inquiry_url || '',
                    payment_url: p.payment_url || '',
                    update_url: p.edit_url || p.update_url || ''
                };
                this.showEditProductModal = true;
            },

            get uniqueCategories() {
                const set = new Set();
                this.products.forEach(p => {
                    if (p.category_name && p.category_name !== '-') set.add(p.category_name);
                });
                return Array.from(set).sort();
            },

            get statusCounts() {
                return {
                    all: this.products.length,
                    active: this.products.filter(p => p.status === 'active').length,
                    inactive: this.products.filter(p => p.status !== 'active').length
                };
            },

            get hasActiveFilters() {
                return !!(this.searchProduct || this.filterCategory || this.filterStatus || this.sortProduct !== 'name_asc');
            },

            resetFilters() {
                this.searchProduct = '';
                this.filterCategory = '';
                this.filterStatus = '';
                this.sortProduct = 'name_asc';
            },

            get filteredProducts() {
                let list = [...this.products];
                const search = (this.searchProduct || '').toLowerCase().trim();
                if (search) {
                    list = list.filter(p =>
                        (p.supplier_sku || '').toLowerCase().includes(search) ||
                        (p.name || '').toLowerCase().includes(search) ||
                        (p.category_name || '').toLowerCase().includes(search)
                    );
                }
                if (this.filterCategory) {
                    list = list.filter(p => (p.category_name || '') === this.filterCategory);
                }
                if (this.filterStatus) {
                    list = list.filter(p => p.status === this.filterStatus);
                }

                if (this.sortProduct === 'name_asc') {
                    list.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
                } else if (this.sortProduct === 'name_desc') {
                    list.sort((a, b) => (b.name || '').localeCompare(a.name || ''));
                } else if (this.sortProduct === 'sku_asc') {
                    list.sort((a, b) => (a.supplier_sku || '').localeCompare(b.supplier_sku || ''));
                } else if (this.sortProduct === 'cost_asc') {
                    list.sort((a, b) => (a.cost_cents || 0) - (b.cost_cents || 0));
                } else if (this.sortProduct === 'cost_desc') {
                    list.sort((a, b) => (b.cost_cents || 0) - (a.cost_cents || 0));
                }

                return list;
            },

            // Upstream Preview Modal State
            showPreviewModal: false,
            previewLoading: false,
            previewImporting: false,
            previewError: '',
            previewProducts: [],
            previewSummary: {
                total_upstream: 0,
                total_new: 0,
                total_existing: 0,
                categories: {}
            },
            selectedPreviewSkus: [],
            previewSearch: '',
            previewCategory: '',
            previewStatusFilter: '',

            async openPreviewModal() {
                this.showPreviewModal = true;
                if (this.previewProducts.length === 0) {
                    await this.loadPreviewProducts();
                }
            },

            async loadPreviewProducts() {
                this.previewLoading = true;
                this.previewError = '';
                try {
                    const res = await fetch(@json(route('admin.providers.products.preview', $provider)), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const data = await res.json();
                    if (!res.ok || !data.success) {
                        throw new Error(data.message || 'Gagal memuat produk dari upstream.');
                    }
                    this.previewProducts = Object.values(data.data.products || {});
                    this.previewSummary = data.data.summary || {
                        total_upstream: 0,
                        total_new: 0,
                        total_existing: 0,
                        categories: {}
                    };
                } catch (err) {
                    this.previewError = err.message || 'Terjadi kesalahan saat menghubungi supplier.';
                } finally {
                    this.previewLoading = false;
                }
            },

            get filteredPreviewProducts() {
                let list = this.previewProducts;
                const search = (this.previewSearch || '').toLowerCase().trim();
                if (search) {
                    list = list.filter(p =>
                        (p.supplier_sku || '').toLowerCase().includes(search) ||
                        (p.name || '').toLowerCase().includes(search) ||
                        (p.category_name || '').toLowerCase().includes(search)
                    );
                }
                if (this.previewCategory) {
                    list = list.filter(p => (p.category_name || '') === this.previewCategory);
                }
                if (this.previewStatusFilter === 'open') {
                    list = list.filter(p => (p.raw_status || '').toUpperCase() === 'OPEN');
                } else if (this.previewStatusFilter === 'new') {
                    list = list.filter(p => !p.is_existing);
                } else if (this.previewStatusFilter === 'existing') {
                    list = list.filter(p => p.is_existing);
                }
                return list;
            },

            get isAllVisibleSelected() {
                const visible = this.filteredPreviewProducts;
                if (visible.length === 0) return false;
                return visible.every(p => this.selectedPreviewSkus.includes(p.supplier_sku));
            },

            toggleSelectAllVisible() {
                const visible = this.filteredPreviewProducts;
                if (this.isAllVisibleSelected) {
                    const visibleSkus = new Set(visible.map(p => p.supplier_sku));
                    this.selectedPreviewSkus = this.selectedPreviewSkus.filter(sku => !visibleSkus.has(sku));
                } else {
                    const currentSet = new Set(this.selectedPreviewSkus);
                    visible.forEach(p => currentSet.add(p.supplier_sku));
                    this.selectedPreviewSkus = Array.from(currentSet);
                }
            },

            selectAllNew() {
                const currentSet = new Set(this.selectedPreviewSkus);
                this.previewProducts.forEach(p => {
                    if (!p.is_existing) {
                        currentSet.add(p.supplier_sku);
                    }
                });
                this.selectedPreviewSkus = Array.from(currentSet);
            },

            clearSelection() {
                this.selectedPreviewSkus = [];
            },

            async submitImportSelected() {
                if (this.selectedPreviewSkus.length === 0) return;
                const count = this.selectedPreviewSkus.length;
                if (!confirm(`Import ${count} produk terpilih ke sistem? Produk baru akan ditambahkan dan produk terdaftar akan diperbarui harga & statusnya.`)) {
                    return;
                }

                this.previewImporting = true;
                try {
                    const res = await fetch(@json(route('admin.providers.products.import_selected', $provider)), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            selected_skus: this.selectedPreviewSkus
                        })
                    });
                    const data = await res.json();
                    if (!res.ok || !data.success) {
                        throw new Error(data.message || 'Gagal mengimpor produk.');
                    }
                    alert(data.message || 'Berhasil mengimpor produk terpilih.');
                    window.location.href = @json(route('admin.providers.show', [$provider, 'tab' => 'products']));
                } catch (err) {
                    alert('Gagal: ' + (err.message || 'Terjadi kesalahan sistem.'));
                    this.previewImporting = false;
                }
            }
        };
    }
    window.providerDetailPage = providerDetailPage;
    if (window.Alpine) {
        window.Alpine.data('providerDetailPage', providerDetailPage);
    }
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('providerDetailPage', providerDetailPage);
    });
    </script>

    <div class="space-y-6 max-w-6xl mx-auto" x-data="providerDetailPage()">

        @if($errors->any())
            <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm">
                <div class="font-bold mb-1">Terdapat kesalahan pengisian formulir:</div>
                <ul class="list-disc list-inside space-y-0.5 text-xs text-rose-300">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Top Navigation & Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-slate-900/60 backdrop-blur-md p-6 rounded-2xl border border-slate-800">
            <div class="flex items-center gap-4">
                <a href="{{ route('admin.providers.index') }}"
                   class="p-2.5 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700/60 text-slate-400 hover:text-white transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h1 class="text-2xl font-bold tracking-tight text-white">{{ $provider->name }}</h1>
                        <span class="font-mono text-xs px-2.5 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-bold">
                            {{ $provider->code }}
                        </span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold {{ $provider->is_active ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $provider->is_active ? 'bg-emerald-400' : 'bg-rose-400' }} mr-1.5"></span>
                            {{ $provider->is_active ? 'Aktif Melayani' : 'Nonaktif (Suspended)' }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">
                        Driver: <span class="font-mono text-slate-300">{{ $provider->driver }}</span> &bull;
                        Prioritas Gateway: <span class="text-indigo-400 font-bold">#{{ $provider->priority }}</span> &bull;
                        Max Workers: <span class="text-slate-300 font-semibold">{{ $provider->max_workers }}</span>
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                {{-- Cek Saldo Upstream --}}
                <form action="{{ route('admin.providers.check_balance', $provider) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-semibold transition-all shadow-sm">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Cek Saldo Upstream</span>
                    </button>
                </form>

                {{-- Ping Health Check --}}
                <div x-data="{ pinging: false, pingResult: {{ Js::from($lastPing) }} }">
                    <button type="button"
                            @click="pinging = true; fetch('{{ route('admin.providers.ping', $provider) }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } }).then(r => r.json()).then(d => pingResult = d).finally(() => pinging = false)"
                            :disabled="pinging"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-semibold transition-all">
                        <svg :class="pinging ? 'animate-spin text-blue-400' : 'text-slate-400'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <span x-text="pinging ? 'Uji Koneksi...' : (pingResult ? 'Ping: ' + pingResult.latency_ms + 'ms' : 'Uji Ping')">Uji Ping</span>
                    </button>
                </div>

                {{-- Toggle Status --}}
                <button type="button"
                        @click="showToggleModal = true"
                        class="px-3.5 py-2 rounded-xl text-xs font-semibold border transition-all {{ $provider->is_active ? 'bg-rose-500/10 text-rose-400 border-rose-500/20 hover:bg-rose-500/20' : 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20 hover:bg-emerald-500/20' }}">
                    {{ $provider->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                </button>
            </div>
        </div>

        {{-- Dynamic Callback URL Banner (Compact Ribbon) --}}
        <div class="bg-indigo-950/40 border border-indigo-500/20 rounded-xl px-4 py-3 flex flex-col md:flex-row md:items-center justify-between gap-3 shadow-sm">
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <span class="inline-block w-2 h-2 rounded-full bg-indigo-400 animate-pulse"></span>
                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-300 whitespace-nowrap">Callback Webhook:</span>
                </div>
                <div class="font-mono text-xs font-bold text-white bg-slate-950/80 px-3 py-1 rounded-lg border border-slate-800 select-all">
                    {{ $provider->getEffectiveCallbackUrl() }}
                </div>
                <span class="text-[11px] text-slate-400 hidden lg:inline">Berikan URL ini ke supplier untuk notifikasi status otomatis</span>
            </div>
            <div>
                <button type="button"
                        @click="copyCallbackUrl('{{ $provider->getEffectiveCallbackUrl() }}')"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-sm transition-all whitespace-nowrap">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                    <span x-text="copied ? '✓ URL Tersalin!' : 'Salin URL'">Salin URL</span>
                </button>
            </div>
        </div>

        {{-- Navigation Tabs --}}
        <div class="flex border-b border-slate-800 space-x-2">
            <button type="button"
                    @click="activeTab = 'credentials'"
                    :class="activeTab === 'credentials' ? 'border-indigo-500 text-indigo-400 bg-slate-900/60' : 'border-transparent text-slate-400 hover:text-slate-300 hover:border-slate-700'"
                    class="py-3 px-5 border-b-2 font-semibold text-xs rounded-t-xl transition-all flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <span>Kredensial & Gateway API</span>
            </button>

            <button type="button"
                    @click="activeTab = 'products'"
                    :class="activeTab === 'products' ? 'border-indigo-500 text-indigo-400 bg-slate-900/60' : 'border-transparent text-slate-400 hover:text-slate-300 hover:border-slate-700'"
                    class="py-3 px-5 border-b-2 font-semibold text-xs rounded-t-xl transition-all flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                <span>Katalog Produk Upstream</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-800 text-slate-300">{{ $provider->upstreamProducts->count() }}</span>
            </button>

            <button type="button"
                    @click="activeTab = 'errors'"
                    :class="activeTab === 'errors' ? 'border-indigo-500 text-indigo-400 bg-slate-900/60' : 'border-transparent text-slate-400 hover:text-slate-300 hover:border-slate-700'"
                    class="py-3 px-5 border-b-2 font-semibold text-xs rounded-t-xl transition-all flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>Mapping Kode Error</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-800 text-slate-300">{{ $provider->errorMappings->count() }}</span>
            </button>

            <button type="button"
                    @click="activeTab = 'breaker'"
                    :class="activeTab === 'breaker' ? 'border-indigo-500 text-indigo-400 bg-slate-900/60' : 'border-transparent text-slate-400 hover:text-slate-300 hover:border-slate-700'"
                    class="py-3 px-5 border-b-2 font-semibold text-xs rounded-t-xl transition-all flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <span>Circuit Breaker & Keamanan</span>
            </button>
        </div>

        {{-- TAB 1: Kredensial & Gateway API --}}
        <div x-show="activeTab === 'credentials'" class="space-y-6">
            <form action="{{ route('admin.providers.update', $provider) }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')

                {{-- 1. General & Connection Endpoints --}}
                <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 p-6 space-y-5">
                    <h2 class="text-sm font-bold text-white uppercase tracking-wider text-indigo-400 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                        <span>Konfigurasi Endpoint & Identitas Supplier</span>
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Nama Vendor / Supplier <span class="text-rose-400">*</span></label>
                            <input type="text" name="name" value="{{ old('name', $provider->name) }}" required
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Kode Unik Sistem (Code)</label>
                            <input type="text" value="{{ $provider->code }}" disabled
                                   class="w-full bg-slate-950/60 border border-slate-800/80 rounded-xl px-3.5 py-2.5 text-xs text-slate-500 font-mono cursor-not-allowed">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Callback Path / Slug</label>
                            <div class="flex items-center">
                                <span class="bg-slate-800 text-slate-400 text-xs px-2.5 py-2.5 rounded-l-xl border border-r-0 border-slate-800 font-mono">/callback/</span>
                                <input type="text" name="callback_slug" value="{{ old('callback_slug', $provider->callback_slug ?: $provider->code) }}" placeholder="slug..."
                                       class="w-full bg-slate-950 border border-slate-800 rounded-r-xl px-3 py-2.5 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                            </div>
                            <p class="text-[10px] text-slate-500 mt-1">Kosongkan jika ingin default mengikuti code.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Base URL API Gateway Upstream</label>
                            <input type="url" name="base_url" value="{{ old('base_url', $provider->config['base_url'] ?? '') }}" placeholder="https://api.supplier.com/v1"
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Port (Opsional)</label>
                            <input type="number" name="port" value="{{ old('port', $provider->config['port'] ?? '') }}" placeholder="8080 / 443"
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Class Adapter / Controller Driver <span class="text-rose-400">*</span></label>
                        <input type="text" name="driver" value="{{ old('driver', $provider->driver) }}" required
                               placeholder="App\Domain\Ppob\Providers\BaseindoProvider"
                               class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-indigo-300 font-mono focus:outline-none focus:border-indigo-500">
                        <p class="text-[10px] text-slate-500 mt-1">Nama class PHP adapter yang mengeksekusi request ke supplier ini.</p>
                    </div>
                </div>

                {{-- 2. Credentials & Auth Security --}}
                <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 p-6 space-y-5"
                     x-data="{ showSecret: false, showPassword: false }">
                    <h2 class="text-sm font-bold text-white uppercase tracking-wider text-indigo-400 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                        <span>Kredensial Autentikasi (Terenkripsi)</span>
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">User / Member ID / Merchant ID</label>
                            <input type="text" name="username" value="{{ old('username', $provider->config['username'] ?? '') }}" placeholder="MEMBER123..."
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Password / PIN Upstream</label>
                            <div class="relative">
                                <input :type="showPassword ? 'text' : 'password'" name="password" placeholder="{{ !empty($provider->config['password']) ? '•••••••• (Tersimpan aman, isi jika ingin ubah)' : 'Masukkan password...' }}"
                                       class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white font-mono focus:outline-none focus:border-indigo-500 pr-10">
                                <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-2.5 text-slate-500 hover:text-slate-300 text-xs">
                                    <span x-text="showPassword ? 'Hide' : 'Show'"></span>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">API Key Upstream</label>
                            <input type="text" name="api_key" value="{{ old('api_key', $provider->config['api_key'] ?? '') }}" placeholder="API-KEY-XYZ..."
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Secret Key / Signature HMAC</label>
                            <div class="relative">
                                <input :type="showSecret ? 'text' : 'password'" name="secret_key" placeholder="{{ !empty($provider->config['secret_key']) ? '•••••••• (Tersimpan aman, isi jika ingin ubah)' : 'Masukkan secret key...' }}"
                                       class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white font-mono focus:outline-none focus:border-indigo-500 pr-10">
                                <button type="button" @click="showSecret = !showSecret" class="absolute right-3 top-2.5 text-slate-500 hover:text-slate-300 text-xs">
                                    <span x-text="showSecret ? 'Hide' : 'Show'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 3. Routing Limits & Operational Parameters --}}
                <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 p-6 space-y-5">
                    <h2 class="text-sm font-bold text-white uppercase tracking-wider text-indigo-400 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                        <span>Parameter Antrean, Rate Limit, & Fitur</span>
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Prioritas (#1 Utama) <span class="text-rose-400">*</span></label>
                            <input type="number" name="priority" value="{{ old('priority', $provider->priority) }}" min="1" max="255" required
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Rate Limit RPM <span class="text-rose-400">*</span></label>
                            <input type="number" name="rate_limit_per_minute" value="{{ old('rate_limit_per_minute', $provider->rate_limit_per_minute) }}" min="1" max="10000" required
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Max Workers <span class="text-rose-400">*</span></label>
                            <input type="number" name="max_workers" value="{{ old('max_workers', $provider->max_workers) }}" min="1" max="100" required
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Timeout (Detik) <span class="text-rose-400">*</span></label>
                            <input type="number" name="timeout_seconds" value="{{ old('timeout_seconds', $provider->timeout_seconds) }}" min="1" max="300" required
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2 border-t border-slate-800">
                        <div class="space-y-3">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="supports_callback" value="1" {{ ($provider->config['supports_callback'] ?? true) ? 'checked' : '' }}
                                       class="rounded bg-slate-950 border-slate-800 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-xs text-slate-300">Supplier Mendukung Webhook / Callback Realtime</span>
                            </label>

                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="supports_check_status" value="1" {{ ($provider->config['supports_check_status'] ?? true) ? 'checked' : '' }}
                                       class="rounded bg-slate-950 border-slate-800 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-xs text-slate-300">Supplier Mendukung API Check Status / Re-query</span>
                            </label>

                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="supports_balance_api" value="1" {{ $provider->supports_balance_api ? 'checked' : '' }}
                                       class="rounded bg-slate-950 border-slate-800 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-xs text-slate-300">Supplier Mendukung API Cek Saldo Deposit</span>
                            </label>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Ambang Batas Saldo Kritis (Alert Threshold Rp)</label>
                            <input type="number" name="balance_alert_threshold" value="{{ old('balance_alert_threshold', $provider->balance_alert_threshold_cents / 100) }}" placeholder="1000000"
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                            <p class="text-[10px] text-slate-500 mt-1">Sistem akan membunyikan peringatan jika saldo supplier berada di bawah nominal ini.</p>
                        </div>
                    </div>
                </div>

                {{-- Submit Action --}}
                <div class="flex justify-end gap-3">
                    <button type="submit"
                            class="px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg shadow-indigo-600/30 transition-all flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Simpan Konfigurasi & Kredensial</span>
                    </button>
                </div>
            </form>
        </div>

        {{-- TAB 2: Katalog Produk Upstream --}}
        <div x-show="activeTab === 'products'" class="space-y-4">
            <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 shadow-xl overflow-hidden">
                {{-- Header Section --}}
                <div class="p-5 sm:p-6 border-b border-slate-800/80 flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <h2 class="text-lg font-bold text-white tracking-tight">Daftar Produk Upstream Supplier</h2>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-mono"
                                  x-text="products.length + ' Produk'"></span>
                        </div>
                        <p class="text-xs text-slate-400 mt-1">
                            Katalog produk mentah langsung dari upstream supplier untuk mapping SKU internal dan konfigurasi endpoint khusus.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2.5">
                        {{-- Button 1: Jelajahi & Import Upstream (Modal Preview) --}}
                        <button type="button"
                                @click="openPreviewModal()"
                                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg shadow-indigo-600/30 transition-all">
                            <svg class="w-4 h-4 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            <span>+ Jelajahi & Import Produk Upstream</span>
                        </button>

                        {{-- Button 2: Quick Sync Harga & Status Terdaftar --}}
                        <form action="{{ route('admin.providers.sync_existing_products', $provider) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit"
                                    title="Perbarui harga modal dan status buka/tutup untuk produk yang sudah terdaftar di tabel"
                                    class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold border border-slate-700 shadow-sm transition-all">
                                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                <span>Update Harga & Status</span>
                            </button>
                        </form>

                        {{-- Button 3: Tambah Manual --}}
                        <button type="button"
                                @click="showAddProductModal = true"
                                class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold border border-slate-700 shadow-sm transition-all">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            <span>+ Tambah Manual</span>
                        </button>
                    </div>
                </div>

                {{-- Compact Single-Line Toolbar Filter (Clean, No Nested Box) --}}
                <div class="px-5 sm:px-6 py-3.5 bg-slate-950/40 border-b border-slate-800 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                    {{-- Left Controls: Search, Category, Sort & Reset --}}
                    <div class="flex flex-wrap items-center gap-2.5 flex-1">
                        {{-- Search Input --}}
                        <div class="relative flex-1 min-w-[220px] max-w-sm">
                            <input type="text" x-model="searchProduct" placeholder="Cari SKU upstream, nama produk..."
                                   class="w-full bg-slate-900 border border-slate-800 rounded-xl pl-9 pr-8 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all">
                            <svg class="w-4 h-4 text-slate-500 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <button type="button" x-show="searchProduct" @click="searchProduct = ''" class="absolute right-2.5 top-2.5 text-slate-500 hover:text-white text-xs">✕</button>
                        </div>

                        {{-- Kategori Filter --}}
                        <select x-model="filterCategory" class="bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                            <option value="">Semua Kategori</option>
                            @php
                                $existingCategories = $provider->upstreamProducts->pluck('category_name')->filter(fn($c) => $c && $c !== '-')->unique()->sort();
                            @endphp
                            @foreach($existingCategories as $cat)
                                <option value="{{ $cat }}">{{ $cat }}</option>
                            @endforeach
                        </select>

                        {{-- Urutan (Sorting) --}}
                        <select x-model="sortProduct" class="bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                            <option value="name_asc">Nama (A &rarr; Z)</option>
                            <option value="name_desc">Nama (Z &rarr; A)</option>
                            <option value="sku_asc">SKU (A &rarr; Z)</option>
                            <option value="cost_asc">HPP Termurah</option>
                            <option value="cost_desc">HPP Termahal</option>
                        </select>

                        {{-- Reset Filter Button --}}
                        <button type="button" x-show="hasActiveFilters" x-cloak @click="resetFilters()"
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-amber-300 hover:text-white border border-slate-700 text-xs font-semibold transition-all">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            <span>Reset</span>
                        </button>
                    </div>

                    {{-- Right Controls: Status Filter Pills --}}
                    <div class="flex items-center gap-3 self-start lg:self-auto">
                        <div class="inline-flex items-center p-1 rounded-xl bg-slate-900 border border-slate-800">
                            <button type="button"
                                    @click="filterStatus = ''"
                                    :class="filterStatus === '' ? 'bg-indigo-600 text-white shadow-sm font-bold' : 'text-slate-400 hover:text-white font-medium'"
                                    class="px-3 py-1.5 rounded-lg text-xs transition-all flex items-center gap-1.5">
                                <span>Semua</span>
                                <span class="px-1.5 py-0.2 rounded text-[10px]" :class="filterStatus === '' ? 'bg-indigo-700 text-white' : 'bg-slate-800 text-slate-400'" x-text="statusCounts.all"></span>
                            </button>

                            <button type="button"
                                    @click="filterStatus = 'active'"
                                    :class="filterStatus === 'active' ? 'bg-emerald-600 text-white shadow-sm font-bold' : 'text-slate-400 hover:text-white font-medium'"
                                    class="px-3 py-1.5 rounded-lg text-xs transition-all flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full" :class="filterStatus === 'active' ? 'bg-white' : 'bg-emerald-400'"></span>
                                <span>Open</span>
                                <span class="px-1.5 py-0.2 rounded text-[10px]" :class="filterStatus === 'active' ? 'bg-emerald-700 text-white' : 'bg-slate-800 text-slate-400'" x-text="statusCounts.active"></span>
                            </button>

                            <button type="button"
                                    @click="filterStatus = 'inactive'"
                                    :class="filterStatus === 'inactive' ? 'bg-rose-600 text-white shadow-sm font-bold' : 'text-slate-400 hover:text-white font-medium'"
                                    class="px-3 py-1.5 rounded-lg text-xs transition-all flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full" :class="filterStatus === 'inactive' ? 'bg-white' : 'bg-rose-400'"></span>
                                <span>Close</span>
                                <span class="px-1.5 py-0.2 rounded text-[10px]" :class="filterStatus === 'inactive' ? 'bg-rose-700 text-white' : 'bg-slate-800 text-slate-400'" x-text="statusCounts.inactive"></span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Enlarged High-Legibility Products Table --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-800 bg-slate-950/70 text-xs font-bold text-slate-400 uppercase tracking-wider">
                                <th class="py-4 px-5 whitespace-nowrap">SKU Upstream</th>
                                <th class="py-4 px-5">Nama Produk Supplier</th>
                                <th class="py-4 px-5 whitespace-nowrap">Kategori</th>
                                <th class="py-4 px-5 text-right whitespace-nowrap">Modal / HPP Upstream</th>
                                <th class="py-4 px-5 whitespace-nowrap">Custom Endpoint</th>
                                <th class="py-4 px-5 text-center whitespace-nowrap">Status</th>
                                <th class="py-4 px-5 whitespace-nowrap">Terakhir Sinkron</th>
                                <th class="py-4 px-5 text-right whitespace-nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 text-sm">
                            <template x-for="p in filteredProducts" :key="p.id">
                                <tr class="hover:bg-slate-800/40 transition-colors">
                                    {{-- SKU Upstream --}}
                                    <td class="py-4 px-5 font-mono font-bold text-indigo-400 whitespace-nowrap">
                                        <span class="bg-slate-950/80 px-2.5 py-1 rounded-lg border border-slate-800 text-xs font-mono tracking-wide" x-text="p.supplier_sku"></span>
                                    </td>

                                    {{-- Nama Produk Supplier --}}
                                    <td class="py-4 px-5 font-semibold text-white">
                                        <div class="text-sm font-semibold text-white tracking-wide" x-text="p.name"></div>
                                    </td>

                                    {{-- Kategori --}}
                                    <td class="py-4 px-5 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-800 text-slate-300 border border-slate-700" x-text="p.category_name"></span>
                                    </td>

                                    {{-- HPP Upstream --}}
                                    <td class="py-4 px-5 text-right font-mono font-bold text-emerald-400 text-sm whitespace-nowrap" x-text="p.cost_formatted"></td>

                                    {{-- Custom Endpoint --}}
                                    <td class="py-4 px-5 whitespace-nowrap">
                                        <div class="flex items-center gap-1.5">
                                            <template x-if="p.inquiry_url">
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-500/10 border border-amber-500/20 text-amber-300 font-mono text-[11px]"
                                                      :title="'Inquiry URL: ' + p.inquiry_url">
                                                    <span class="font-bold text-[9px] uppercase">Inq:</span>
                                                    <span class="truncate max-w-[120px]" x-text="p.inquiry_url"></span>
                                                </span>
                                            </template>
                                            <template x-if="p.payment_url">
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-cyan-500/10 border border-cyan-500/20 text-cyan-300 font-mono text-[11px]"
                                                      :title="'Payment URL: ' + p.payment_url">
                                                    <span class="font-bold text-[9px] uppercase">Pay:</span>
                                                    <span class="truncate max-w-[120px]" x-text="p.payment_url"></span>
                                                </span>
                                            </template>
                                            <template x-if="!p.inquiry_url && !p.payment_url">
                                                <span class="text-xs text-slate-500 italic">Default Base URL</span>
                                            </template>
                                        </div>
                                    </td>

                                    {{-- Status --}}
                                    <td class="py-4 px-5 text-center whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold"
                                              :class="p.status === 'active' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'">
                                            <span class="w-1.5 h-1.5 rounded-full" :class="p.status === 'active' ? 'bg-emerald-400' : 'bg-rose-400'"></span>
                                            <span x-text="p.status === 'active' ? 'Active (Open)' : 'Inactive (Close)'"></span>
                                        </span>
                                    </td>

                                    {{-- Terakhir Sinkron --}}
                                    <td class="py-4 px-5 text-slate-400 font-mono text-xs whitespace-nowrap" x-text="p.last_synced"></td>

                                    {{-- Aksi --}}
                                    <td class="py-4 px-5 text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button type="button" @click="openEditProduct(p)"
                                                    title="Edit Produk / Custom Endpoint"
                                                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-indigo-300 hover:text-white border border-slate-700 text-xs font-medium transition-all">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                <span>Edit</span>
                                            </button>
                                            <a :href="p.sku_link"
                                               title="Lihat di SKU Internal"
                                               class="inline-flex items-center px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-medium border border-slate-700 transition-all">
                                                <span>SKU</span>
                                            </a>
                                            <form :action="p.delete_url" method="POST"
                                                  onsubmit="return confirm('Hapus produk upstream ini?')" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" title="Hapus Produk Upstream"
                                                        class="p-1.5 rounded-lg bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 border border-rose-500/20 transition-all">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="filteredProducts.length === 0">
                                <td colspan="8" class="py-16 text-center text-slate-500">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <svg class="w-10 h-10 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                        <p class="text-sm font-medium text-slate-300">Tidak ada produk upstream yang cocok dengan filter / pencarian.</p>
                                        <p class="text-xs text-slate-500">Coba ubah kata kunci pencarian, reset filter, atau klik "+ Tambah Produk Manual".</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Table Footer Summary --}}
                <div class="px-5 sm:px-6 py-3 bg-slate-950/60 border-t border-slate-800 text-xs text-slate-400 flex items-center justify-between">
                    <div>
                        Menampilkan <strong class="text-white" x-text="filteredProducts.length"></strong> dari <strong class="text-white" x-text="products.length"></strong> produk upstream
                    </div>
                    <template x-if="filterCategory">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-indigo-500/10 text-indigo-300 border border-indigo-500/20"
                              x-text="'Kategori: ' + filterCategory"></span>
                    </template>
                </div>
            </div>
        </div>

        {{-- TAB 3: Mapping Kode Error --}}
        <div x-show="activeTab === 'errors'" class="space-y-6">
            <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 p-6 shadow-xl space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-800">
                    <div>
                        <h2 class="text-base font-bold text-white flex items-center gap-2">
                            <span>Kamus Pemetaan Kode Error Supplier</span>
                            <span class="text-xs font-normal text-slate-400">({{ $provider->errorMappings->count() }} aturan mapping)</span>
                        </h2>
                        <p class="text-xs text-slate-400 mt-0.5">Petakan Response Code (RC) unik supplier ke Kode Standar Internal kita agar laporan status transaksi konsisten.</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('admin.error_mappings.index', ['provider_id' => $provider->id]) }}"
                           class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold border border-slate-700 shadow-sm transition-all">
                            <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                            <span>Buka Master Kamus RC Global</span>
                        </a>

                        <button type="button"
                                @click="showAddErrorModal = true"
                                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg shadow-indigo-600/30 transition-all">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            <span>+ Tambah Pemetaan Error Code</span>
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-800 bg-slate-950/60 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                                <th class="py-3 px-3">RC Upstream</th>
                                <th class="py-3 px-3">Kode Error Internal</th>
                                <th class="py-3 px-3">Pesan Ramah Pengguna (Custom Message)</th>
                                <th class="py-3 px-3 text-center">Bisa Retry?</th>
                                <th class="py-3 px-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            @forelse($provider->errorMappings as $em)
                                <tr class="hover:bg-slate-800/40 transition-colors">
                                    <td class="py-3 px-3 font-mono font-bold text-amber-400 text-sm">
                                        <span class="bg-slate-950 px-2 py-0.5 rounded border border-slate-800">{{ $em->upstream_code }}</span>
                                    </td>
                                    <td class="py-3 px-3 font-mono font-bold">
                                        <div class="flex items-center gap-1.5">
                                            @if(!empty($standardErrors[$em->internal_code]['rc']))
                                                <span class="px-1.5 py-0.5 rounded bg-slate-800 text-indigo-300 border border-slate-700 text-[10px]">RC {{ $standardErrors[$em->internal_code]['rc'] }}</span>
                                            @endif
                                            <span class="px-2 py-1 rounded text-[11px] {{ $em->internal_code === 'SUCCESS' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' }}">
                                                {{ $em->internal_code }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-3 text-slate-200">
                                        {{ $em->custom_message ?: ($standardErrors[$em->internal_code]['default_message'] ?? '-') }}
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold {{ $em->is_retryable ? 'bg-indigo-500/10 text-indigo-300 border border-indigo-500/20' : 'bg-slate-800 text-slate-400' }}">
                                            {{ $em->is_retryable ? 'Ya (Retryable)' : 'Tidak' }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-right space-x-1 whitespace-nowrap">
                                        <button type="button"
                                                @click="openEditError({
                                                    id: {{ $em->id }},
                                                    upstream_code: '{{ addslashes($em->upstream_code) }}',
                                                    internal_code: '{{ $em->internal_code }}',
                                                    custom_message: '{{ addslashes($em->custom_message ?? '') }}',
                                                    is_retryable: {{ $em->is_retryable ? 'true' : 'false' }}
                                                })"
                                                class="p-1.5 rounded-lg bg-slate-800 text-indigo-300 hover:text-white hover:bg-slate-700 border border-slate-700 transition-all"
                                                title="Edit Mapping RC">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <form action="{{ route('admin.providers.error_mappings.destroy', ['provider' => $provider, 'mapping' => $em]) }}" method="POST"
                                              onsubmit="return confirm('Hapus pemetaan error code [{{ $em->upstream_code }}]?')" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 rounded-lg bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 border border-rose-500/20 transition-all" title="Hapus Mapping">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-12 text-center text-slate-500">
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <svg class="w-8 h-8 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                            <p class="text-sm">Belum ada pemetaan kode error khusus untuk supplier ini.</p>
                                            <p class="text-xs text-slate-600">Klik tombol "+ Tambah Pemetaan Error Code" di atas untuk menambahkan mapping Response Code supplier.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- TAB 4: Circuit Breaker & Keamanan --}}
        <div x-show="activeTab === 'breaker'" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                {{-- Circuit Breaker Card --}}
                <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 p-5 shadow-xl space-y-4">
                    <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Status Circuit Breaker</h2>
                    <div>
                        @php
                            $cbState = $provider->circuit_state ?? 'CLOSED';
                        @endphp
                        <div class="text-3xl font-mono font-bold {{ $cbState === 'OPEN' ? 'text-rose-400' : ($cbState === 'HALF-OPEN' ? 'text-amber-400' : 'text-emerald-400') }}">
                            {{ $cbState }}
                        </div>
                        <p class="text-xs text-slate-400 mt-2">
                            {{ $cbState === 'OPEN' ? 'Traffic dialihkan fail-fast karena error rate tinggi.' : ($cbState === 'HALF-OPEN' ? 'Masa cooldown selesai, uji coba 1 request.' : 'Operasional normal, melayani seluruh request transaksi.') }}
                        </p>
                    </div>
                    <div class="pt-2">
                        <button type="button" @click="showResetModal = true"
                                class="w-full px-3.5 py-2 rounded-xl bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 border border-amber-500/20 text-xs font-semibold transition-all">
                            Reset Breaker ke CLOSED
                        </button>
                    </div>
                </div>

                {{-- Circuit Breaker Threshold Settings Card --}}
                <div class="md:col-span-2 bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 p-6 shadow-xl space-y-4">
                    <h2 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span>Konfigurasi Ambang Batas Breaker</span>
                    </h2>

                    <form method="POST" action="{{ route('admin.providers.circuit_breaker.config', $provider) }}" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="show_failure_threshold" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                                    Batas Gagal Trip (Request)
                                </label>
                                <input type="number" id="show_failure_threshold" name="failure_threshold"
                                       value="{{ $provider->config['circuit_breaker']['failure_threshold'] ?? 5 }}" min="1" max="50" required
                                       class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 font-mono focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            </div>

                            <div>
                                <label for="show_cooldown_seconds" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                                    Cooldown Cooldown (Detik)
                                </label>
                                <input type="number" id="show_cooldown_seconds" name="cooldown_seconds"
                                       value="{{ $provider->config['circuit_breaker']['cooldown_seconds'] ?? ($provider->timeout_seconds ?? 60) }}" min="10" max="600" required
                                       class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 font-mono focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            </div>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition-all">
                                Simpan Ambang Batas
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Incident & Activity Logs Card --}}
            <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h2 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Log Insiden & Intervensi Gateway</span>
                    </h2>
                </div>

                <div class="overflow-x-auto max-h-[280px] overflow-y-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-800 bg-slate-950/60 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">
                                <th class="py-2.5 px-3">Waktu</th>
                                <th class="py-2.5 px-3">Admin</th>
                                <th class="py-2.5 px-3">Aksi</th>
                                <th class="py-2.5 px-3">Catatan / Alasan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            @forelse($incidentLogs as $log)
                                <tr class="hover:bg-slate-800/40">
                                    <td class="py-2.5 px-3 text-[11px] text-slate-400 font-mono whitespace-nowrap">
                                        {{ $log->created_at?->format('d/m/Y H:i:s') }}
                                    </td>
                                    <td class="py-2.5 px-3 text-white font-medium">
                                        {{ $log->admin?->name ?? 'System' }}
                                    </td>
                                    <td class="py-2.5 px-3 font-mono text-[11px] text-amber-300 whitespace-nowrap">
                                        {{ $log->action }}
                                    </td>
                                    <td class="py-2.5 px-3 text-slate-300 text-xs">
                                        {{ $log->reason ?? '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-8 text-center text-slate-500 text-xs">
                                        Belum ada riwayat insiden untuk provider ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Modal Tambah Mapping Error --}}
        <div x-show="showAddErrorModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="showAddErrorModal = false"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-lg rounded-2xl bg-slate-900 border border-slate-800 p-6 shadow-2xl space-y-4">
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Tambah / Edit Pemetaan Kode Error Supplier</span>
                    </h3>
                    <p class="text-xs text-slate-400">Petakan Response Code (RC) dari {{ $provider->name }} ke kode standar internal dan pesan ramah yang tampil ke pengguna.</p>

                    <form action="{{ route('admin.providers.error_mappings.store', $provider) }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Response Code (RC) Upstream Supplier <span class="text-rose-400">*</span></label>
                            <input type="text" name="upstream_code" placeholder="Contoh: 01, 42, 55, dll" required
                                   class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Kode Standar Error Internal <span class="text-rose-400">*</span></label>
                            <select name="internal_code" required
                                    class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                                @foreach($standardErrors as $code => $def)
                                    <option value="{{ $code }}">[RC {{ $def['rc'] ?? '--' }}] {{ $code }} &bull; {{ $def['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Pesan Khusus ke Klien / Aplikasi Mobile (Opsional)</label>
                            <input type="text" name="custom_message" placeholder="Kosongkan jika ingin memakai pesan default standar..."
                                   class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                        </div>

                        <div>
                            <label class="flex items-center gap-2.5 cursor-pointer">
                                <input type="checkbox" name="is_retryable" value="1"
                                       class="rounded bg-slate-950 border-slate-700 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-xs text-slate-300">Dapat Dicoba Ulang Otomatis (Retryable Error)?</span>
                            </label>
                        </div>

                        <div class="flex justify-end gap-3 pt-3 border-t border-slate-800">
                            <button type="button" @click="showAddErrorModal = false" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-medium">Batal</button>
                            <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold shadow-lg shadow-indigo-600/30">Simpan Pemetaan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Modal Edit Mapping Error --}}
        <div x-show="showEditErrorModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="showEditErrorModal = false"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-lg rounded-2xl bg-slate-900 border border-slate-800 p-6 shadow-2xl space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            <span>Edit Pemetaan Kode Error Supplier</span>
                        </h3>
                        <button type="button" @click="showEditErrorModal = false" class="text-slate-400 hover:text-white">✕</button>
                    </div>

                    <form :action="'{{ url('/office/error-mappings') }}/' + editingError.id" method="POST" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Response Code (RC) Upstream Supplier <span class="text-rose-400">*</span></label>
                            <input type="text" name="upstream_code" x-model="editingError.upstream_code" required
                                   class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Kode Standar Error Internal <span class="text-rose-400">*</span></label>
                            <select name="internal_code" x-model="editingError.internal_code" required
                                    class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                                @foreach($standardErrors as $code => $def)
                                    <option value="{{ $code }}">[RC {{ $def['rc'] ?? '--' }}] {{ $code }} &bull; {{ $def['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Pesan Khusus ke Klien / Aplikasi Mobile (Opsional)</label>
                            <input type="text" name="custom_message" x-model="editingError.custom_message" placeholder="Kosongkan jika ingin memakai pesan default standar..."
                                   class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                        </div>

                        <div>
                            <label class="flex items-center gap-2.5 cursor-pointer">
                                <input type="checkbox" name="is_retryable" value="1" :checked="editingError.is_retryable"
                                       class="rounded bg-slate-950 border-slate-700 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-xs text-slate-300">Dapat Dicoba Ulang Otomatis (Retryable Error)?</span>
                            </label>
                        </div>

                        <div class="flex justify-end gap-3 pt-3 border-t border-slate-800">
                            <button type="button" @click="showEditErrorModal = false" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-medium">Batal</button>
                            <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold shadow-lg shadow-indigo-600/30">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Modal Tambah Produk Upstream Manual --}}
        <div x-show="showAddProductModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="showAddProductModal = false"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-lg rounded-2xl bg-slate-900 border border-slate-800 p-6 shadow-2xl space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            <span>Tambah Produk Upstream Manual</span>
                        </h3>
                        <button type="button" @click="showAddProductModal = false" class="text-slate-400 hover:text-white">✕</button>
                    </div>

                    <form action="{{ route('admin.providers.products.store', $provider) }}" method="POST" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">SKU Supplier / Upstream <span class="text-rose-400">*</span></label>
                                <input type="text" name="supplier_sku" placeholder="Contoh: DANA50, PLN100" required
                                       class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">Kategori Produk</label>
                                <input type="text" name="category_name" placeholder="E-Wallet, PLN, Pulsa..."
                                       class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Produk Supplier <span class="text-rose-400">*</span></label>
                            <input type="text" name="name" placeholder="Contoh: DANA Saldo Rp 50.000" required
                                   class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">Modal / HPP (Rupiah) <span class="text-rose-400">*</span></label>
                                <input type="number" name="cost" placeholder="50000" min="0" required
                                       class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">Status Operasional</label>
                                <select name="status" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                                    <option value="active">Active (Open)</option>
                                    <option value="inactive">Inactive (Close)</option>
                                </select>
                            </div>
                        </div>

                        <div class="p-3 rounded-xl bg-slate-950/70 border border-slate-800/80 space-y-3">
                            <div class="text-[11px] font-bold text-amber-400 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Custom Endpoint URL (Opsional)</span>
                            </div>
                            <p class="text-[10px] text-slate-400 leading-relaxed">
                                Isi jika supplier memiliki URL path berbeda per produk atau kategori (misal PLN ke <code class="text-indigo-300">/v1/pln</code>, E-Wallet ke <code class="text-indigo-300">/ewallet</code>). Bisa berupa path awalan slash <code class="text-indigo-300">/path</code> atau full URL <code class="text-indigo-300">https://...</code>. Kosongkan jika memakai endpoint default provider.
                            </p>

                            <div>
                                <label class="block text-[11px] font-semibold text-slate-300 mb-1">URL Khusus Inquiry / Cek Tagihan</label>
                                <input type="text" name="inquiry_url" placeholder="/v1/inquiry/pln atau /api/check"
                                       class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                            </div>

                            <div>
                                <label class="block text-[11px] font-semibold text-slate-300 mb-1">URL Khusus Payment / Transaksi</label>
                                <input type="text" name="payment_url" placeholder="/v1/order/ewallet atau /api/trx"
                                       class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-3 border-t border-slate-800">
                            <button type="button" @click="showAddProductModal = false" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-medium">Batal</button>
                            <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold shadow-lg shadow-indigo-600/30">Simpan Produk Upstream</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Modal Edit Produk Upstream --}}
        <div x-show="showEditProductModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="showEditProductModal = false"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-lg rounded-2xl bg-slate-900 border border-slate-800 p-6 shadow-2xl space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            <span>Edit Produk Upstream</span>
                        </h3>
                        <button type="button" @click="showEditProductModal = false" class="text-slate-400 hover:text-white">✕</button>
                    </div>

                    <form :action="editingProduct.update_url" method="POST" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">SKU Supplier / Upstream <span class="text-rose-400">*</span></label>
                                <input type="text" name="supplier_sku" x-model="editingProduct.supplier_sku" required
                                       class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">Kategori Produk</label>
                                <input type="text" name="category_name" x-model="editingProduct.category_name"
                                       class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Produk Supplier <span class="text-rose-400">*</span></label>
                            <input type="text" name="name" x-model="editingProduct.name" required
                                   class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">Modal / HPP (Rupiah) <span class="text-rose-400">*</span></label>
                                <input type="number" name="cost" x-model="editingProduct.cost" min="0" required
                                       class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">Status Operasional</label>
                                <select name="status" x-model="editingProduct.status" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                                    <option value="active">Active (Open)</option>
                                    <option value="inactive">Inactive (Close)</option>
                                </select>
                            </div>
                        </div>

                        <div class="p-3 rounded-xl bg-slate-950/70 border border-slate-800/80 space-y-3">
                            <div class="text-[11px] font-bold text-amber-400 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Custom Endpoint URL (Opsional)</span>
                            </div>
                            <p class="text-[10px] text-slate-400 leading-relaxed">
                                Path awalan slash <code class="text-indigo-300">/path</code> akan digabung dengan Base URL provider. Atau masukkan Full URL <code class="text-indigo-300">https://...</code> jika mengarah ke host/server berbeda.
                            </p>

                            <div>
                                <label class="block text-[11px] font-semibold text-slate-300 mb-1">URL Khusus Inquiry / Cek Tagihan</label>
                                <input type="text" name="inquiry_url" x-model="editingProduct.inquiry_url" placeholder="Kosongkan jika default provider"
                                       class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                            </div>

                            <div>
                                <label class="block text-[11px] font-semibold text-slate-300 mb-1">URL Khusus Payment / Transaksi</label>
                                <input type="text" name="payment_url" x-model="editingProduct.payment_url" placeholder="Kosongkan jika default provider"
                                       class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-3 border-t border-slate-800">
                            <button type="button" @click="showEditProductModal = false" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-medium">Batal</button>
                            <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold shadow-lg shadow-indigo-600/30">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Modal Reset Breaker --}}
        <div x-show="showResetModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="showResetModal = false"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-md rounded-2xl bg-slate-900 border border-slate-800 p-6 shadow-2xl">
                    <h3 class="text-base font-bold text-white mb-2">Reset Circuit Breaker</h3>
                    <p class="text-xs text-slate-400 mb-4">Mereset counter kegagalan ke 0 dan mengembalikan breaker provider {{ $provider->name }} ke status CLOSED.</p>
                    <form action="{{ route('admin.providers.circuit_breaker.reset', $provider) }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Catatan Reset</label>
                            <input type="text" name="reason" placeholder="Alasan reset..." class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white">
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="showResetModal = false" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs">Batal</button>
                            <button type="submit" class="px-4 py-2 rounded-xl bg-amber-600 text-white text-xs font-semibold">Eksekusi Reset</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Modal Toggle Status --}}
        <div x-show="showToggleModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="showToggleModal = false"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-md rounded-2xl bg-slate-900 border border-slate-800 p-6 shadow-2xl">
                    <h3 class="text-base font-bold text-white mb-2">Ubah Status Operasional Provider</h3>
                    <p class="text-xs text-slate-400 mb-4">
                        Ubah status provider {{ $provider->name }} menjadi <span class="font-bold text-white">{{ $provider->is_active ? 'NONAKTIF' : 'AKTIF' }}</span>.
                    </p>
                    <form action="{{ route('admin.providers.toggle', $provider) }}" method="POST" class="space-y-4">
                        @csrf
                        <input type="hidden" name="is_active" value="{{ $provider->is_active ? '0' : '1' }}">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Alasan Perubahan (Min 10 karakter) *</label>
                            <textarea name="reason" x-model="toggleReason" required rows="3" placeholder="Sebutkan alasan teknis..." class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white"></textarea>
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="showToggleModal = false" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs">Batal</button>
                            <button type="submit" :disabled="toggleReason.trim().length < 10" class="px-4 py-2 rounded-xl bg-indigo-600 disabled:opacity-50 text-white text-xs font-semibold">Simpan Status</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Modal Preview & Import Produk Upstream --}}
        <div x-show="showPreviewModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-md transition-opacity" @click="if(!previewImporting) showPreviewModal = false"></div>
            
            <div class="flex min-h-full items-center justify-center p-3 sm:p-6">
                <div class="relative w-full max-w-5xl rounded-2xl bg-slate-900 border border-slate-800 shadow-2xl flex flex-col max-h-[90vh] overflow-hidden">
                    
                    {{-- Modal Header --}}
                    <div class="p-5 sm:p-6 border-b border-slate-800 flex items-center justify-between bg-slate-950/60">
                        <div class="flex items-center gap-3">
                            <div class="p-2.5 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            </div>
                            <div>
                                <h3 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
                                    <span>Jelajahi & Import Produk Upstream</span>
                                    <span class="text-xs font-mono font-normal px-2.5 py-0.5 rounded-full bg-slate-800 text-indigo-300 border border-slate-700">{{ $provider->name }}</span>
                                </h3>
                                <p class="text-xs text-slate-400 mt-0.5">
                                    Pilih produk yang ingin Anda daftarkan ke katalog upstream sistem. Produk yang sudah terdaftar akan memperbarui harga & statusnya.
                                </p>
                            </div>
                        </div>
                        <button type="button" @click="if(!previewImporting) showPreviewModal = false" class="p-2 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition-all">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    {{-- Stats Summary Bar (When Loaded) --}}
                    <div x-show="!previewLoading && !previewError" class="px-5 sm:px-6 py-3 bg-slate-950/40 border-b border-slate-800/80 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="text-slate-400">Total Upstream:</span>
                            <span class="font-bold text-white font-mono" x-text="previewSummary.total_upstream">0</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-slate-400">Belum Terdaftar (Baru):</span>
                            <span class="font-bold text-indigo-400 font-mono" x-text="previewSummary.total_new">0</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-slate-400">Sudah Terdaftar:</span>
                            <span class="font-bold text-slate-300 font-mono" x-text="previewSummary.total_existing">0</span>
                        </div>
                        <div class="flex items-center gap-2 sm:justify-end">
                            <span class="text-slate-400">Dipilih:</span>
                            <span class="font-bold text-emerald-400 font-mono bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20" x-text="selectedPreviewSkus.length + ' Produk'">0 Produk</span>
                        </div>
                    </div>

                    {{-- Toolbar Filters (When Loaded) --}}
                    <div x-show="!previewLoading && !previewError" class="px-5 sm:px-6 py-3 border-b border-slate-800 flex flex-col md:flex-row md:items-center md:justify-between gap-3 bg-slate-900/80">
                        <div class="flex flex-wrap items-center gap-2.5 flex-1">
                            {{-- Search --}}
                            <div class="relative flex-1 min-w-[200px] max-w-xs">
                                <input type="text" x-model="previewSearch" placeholder="Cari SKU, nama, operator..."
                                       class="w-full bg-slate-950 border border-slate-800 rounded-xl pl-8 pr-7 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                                <svg class="w-3.5 h-3.5 text-slate-500 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                <button type="button" x-show="previewSearch" @click="previewSearch = ''" class="absolute right-2 top-1.5 text-slate-500 hover:text-white text-xs">✕</button>
                            </div>

                            {{-- Kategori / Operator Filter (Styled Alpine Dropdown) --}}
                            <div class="relative" x-data="{ open: false }">
                                <button type="button" @click="open = !open"
                                        class="inline-flex items-center justify-between gap-2 bg-slate-950 border border-slate-800 rounded-xl px-2.5 py-1.5 text-xs text-white hover:border-slate-700 focus:outline-none min-w-[140px]">
                                    <span x-text="previewCategory ? previewCategory + (previewSummary.categories && previewSummary.categories[previewCategory] ? ' (' + previewSummary.categories[previewCategory] + ')' : '') : 'Semua Kategori'" class="truncate">Semua Kategori</span>
                                    <svg class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div x-show="open" @click.away="open = false" x-cloak
                                     class="absolute left-0 mt-1 w-52 rounded-xl bg-slate-900 border border-slate-800 shadow-2xl py-1 z-30 max-h-60 overflow-y-auto">
                                    <button type="button" @click="previewCategory = ''; open = false"
                                            class="w-full text-left px-3 py-1.5 text-xs text-slate-300 hover:bg-slate-800 flex items-center justify-between"
                                            :class="!previewCategory ? 'bg-indigo-600/20 text-indigo-400 font-bold' : ''">
                                        <span>Semua Kategori</span>
                                        <span class="text-[10px] text-slate-500 font-mono" x-text="previewSummary.total_upstream || 0"></span>
                                    </button>
                                    <template x-for="(count, cat) in previewSummary.categories" :key="cat">
                                        <button type="button" @click="previewCategory = cat; open = false"
                                                class="w-full text-left px-3 py-1.5 text-xs text-slate-300 hover:bg-slate-800 flex items-center justify-between"
                                                :class="previewCategory === cat ? 'bg-indigo-600/20 text-indigo-400 font-bold' : ''">
                                            <span x-text="cat" class="truncate mr-2"></span>
                                            <span class="text-[10px] text-slate-500 font-mono" x-text="count"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            {{-- Status Filter --}}
                            <select x-model="previewStatusFilter" class="bg-slate-950 border border-slate-800 rounded-xl px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                                <option value="">Semua Status</option>
                                <option value="open">Hanya OPEN</option>
                                <option value="new">Hanya Produk Baru</option>
                                <option value="existing">Hanya Sudah Terdaftar</option>
                            </select>
                        </div>

                        {{-- Quick Selection Buttons --}}
                        <div class="flex items-center gap-2">
                            <button type="button" @click="toggleSelectAllVisible()"
                                    class="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-[11px] font-semibold transition-all">
                                <span x-text="isAllVisibleSelected ? 'Batal Pilih Semua' : 'Pilih Semua (Tampil)'">Pilih Semua</span>
                            </button>
                            <button type="button" @click="selectAllNew()"
                                    class="px-2.5 py-1.5 rounded-lg bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 text-[11px] font-semibold transition-all">
                                <span>Pilih Semua yang Baru</span>
                            </button>
                            <button type="button" x-show="selectedPreviewSkus.length > 0" @click="clearSelection()"
                                    class="px-2.5 py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 border border-rose-500/30 text-[11px] font-semibold transition-all">
                                <span>Reset Pilihan</span>
                            </button>
                        </div>
                    </div>

                    {{-- Modal Body: Loading State / Error State / Products Table --}}
                    <div class="flex-1 overflow-y-auto min-h-[320px] max-h-[55vh]">
                        {{-- Loading Spinner --}}
                        <div x-show="previewLoading" class="py-16 flex flex-col items-center justify-center space-y-4">
                            <div class="w-10 h-10 border-4 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
                            <div class="text-center">
                                <p class="text-sm font-bold text-white">Menghubungkan ke Gateway {{ $provider->name }}...</p>
                                <p class="text-xs text-slate-400 mt-1">Mengambil katalog produk resmi secara live (ProductInquiry)...</p>
                            </div>
                        </div>

                        {{-- Error Banner --}}
                        <div x-show="previewError && !previewLoading" class="p-8 text-center space-y-4">
                            <div class="w-12 h-12 mx-auto rounded-full bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-white">Gagal Menghubungi Supplier</h4>
                                <p class="text-xs text-rose-400 mt-1 font-mono" x-text="previewError"></p>
                            </div>
                            <button type="button" @click="openPreviewModal()"
                                    class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold border border-slate-700">
                                Coba Hubungi Ulang
                            </button>
                        </div>

                        {{-- Table of Products --}}
                        <div x-show="!previewLoading && !previewError">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead class="bg-slate-950/80 sticky top-0 z-10 border-b border-slate-800">
                                    <tr class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                                        <th class="py-3 px-4 w-10 text-center">
                                            <input type="checkbox" :checked="isAllVisibleSelected" @change="toggleSelectAllVisible()"
                                                   class="rounded bg-slate-950 border-slate-700 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                                        </th>
                                        <th class="py-3 px-4">SKU Supplier</th>
                                        <th class="py-3 px-4">Nama Produk</th>
                                        <th class="py-3 px-4">Operator / Kategori</th>
                                        <th class="py-3 px-4 text-right">Modal Supplier</th>
                                        <th class="py-3 px-4 text-center">Status Upstream</th>
                                        <th class="py-3 px-4 text-center">Status Sistem</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800/60">
                                    <template x-for="p in filteredPreviewProducts" :key="p.supplier_sku">
                                        <tr class="hover:bg-slate-800/40 transition-colors cursor-pointer"
                                            @click="if ($event.target.tagName !== 'INPUT') {
                                                const idx = selectedPreviewSkus.indexOf(p.supplier_sku);
                                                if (idx > -1) {
                                                    selectedPreviewSkus.splice(idx, 1);
                                                    selectedPreviewSkus = [...selectedPreviewSkus];
                                                } else {
                                                    selectedPreviewSkus.push(p.supplier_sku);
                                                    selectedPreviewSkus = [...selectedPreviewSkus];
                                                }
                                            }">
                                            <td class="py-3 px-4 text-center" @click.stop>
                                                <input type="checkbox" :value="p.supplier_sku" x-model="selectedPreviewSkus"
                                                       class="rounded bg-slate-950 border-slate-700 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                                            </td>
                                            <td class="py-3 px-4 font-mono font-bold text-white" x-text="p.supplier_sku"></td>
                                            <td class="py-3 px-4 text-slate-200" x-text="p.name"></td>
                                            <td class="py-3 px-4">
                                                <span class="px-2 py-0.5 rounded-md bg-slate-800 text-slate-300 font-medium text-[10px]" x-text="p.category_name"></span>
                                            </td>
                                            <td class="py-3 px-4 text-right font-mono">
                                                <div class="font-bold text-white" x-text="p.cost_formatted"></div>
                                                <template x-if="p.is_existing && p.price_diff !== 0">
                                                    <div class="text-[10px]" :class="p.price_diff > 0 ? 'text-rose-400' : 'text-emerald-400'"
                                                         x-text="'Lama: ' + p.existing_cost_formatted + ' (' + p.price_diff_formatted + ')'"></div>
                                                </template>
                                            </td>
                                            <td class="py-3 px-4 text-center">
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                                      :class="p.raw_status === 'OPEN' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'"
                                                      x-text="p.raw_status"></span>
                                            </td>
                                            <td class="py-3 px-4 text-center">
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                                      :class="p.is_existing ? 'bg-slate-800 text-slate-400' : 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20'"
                                                      x-text="p.is_existing ? 'Terdaftar' : 'Baru'"></span>
                                            </td>
                                        </tr>
                                    </template>
                                    <template x-if="filteredPreviewProducts.length === 0">
                                        <tr>
                                            <td colspan="7" class="py-12 text-center text-slate-500">
                                                Tidak ada produk upstream yang cocok dengan filter pencarian.
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Modal Footer --}}
                    <div class="p-4 sm:p-5 border-t border-slate-800 bg-slate-950/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="text-xs text-slate-400">
                            <span class="font-bold text-white font-mono" x-text="selectedPreviewSkus.length">0</span> produk terpilih dari <span class="font-mono" x-text="previewProducts.length">0</span> produk upstream.
                        </div>

                        <div class="flex items-center gap-2.5 justify-end">
                            <button type="button" @click="if(!previewImporting) showPreviewModal = false"
                                    class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-all">
                                Tutup
                            </button>

                            <button type="button" @click="submitImportSelected()"
                                    :disabled="selectedPreviewSkus.length === 0 || previewImporting"
                                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-bold shadow-lg shadow-indigo-600/30 transition-all">
                                <template x-if="previewImporting">
                                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                </template>
                                <span x-text="previewImporting ? 'Mengimpor Produk...' : ('Import ' + selectedPreviewSkus.length + ' Produk Terpilih')">Import Produk</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin.layouts.app>
