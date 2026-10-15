<x-admin.layouts.app>
    <div class="space-y-6 max-w-6xl mx-auto"
         x-data="{
            activeTab: '{{ request('tab', 'credentials') }}',
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
            }
         }">

        {{-- Flash Alerts --}}
        @if(session('success'))
            <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>{{ session('success') }}</span>
                </div>
                <button type="button" @click="$el.parentElement.remove()" class="text-emerald-400/70 hover:text-emerald-400">✕</button>
            </div>
        @endif

        @if(session('error'))
            <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    <span>{{ session('error') }}</span>
                </div>
                <button type="button" @click="$el.parentElement.remove()" class="text-rose-400/70 hover:text-rose-400">✕</button>
            </div>
        @endif

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

        {{-- Dynamic Callback URL Banner --}}
        <div class="bg-gradient-to-r from-indigo-950/60 to-slate-900 border border-indigo-500/20 rounded-2xl p-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="inline-block w-2 h-2 rounded-full bg-indigo-400 animate-pulse"></span>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-indigo-300">Dedicated Callback Webhook URL</h3>
                </div>
                <p class="text-xs text-slate-400">Berikan URL ini ke pihak supplier {{ $provider->name }} untuk menerima notifikasi status transaksi otomatis:</p>
                <div class="font-mono text-sm font-bold text-white bg-slate-950 px-3 py-1.5 rounded-xl border border-slate-800 select-all inline-block mt-1">
                    {{ $provider->getEffectiveCallbackUrl() }}
                </div>
            </div>
            <div>
                <button type="button"
                        @click="copyCallbackUrl('{{ $provider->getEffectiveCallbackUrl() }}')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                    <span x-text="copied ? '✓ URL Tersalin!' : 'Salin URL Callback'">Salin URL Callback</span>
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
        <div x-show="activeTab === 'products'" class="space-y-6">
            <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 p-6 shadow-xl space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-800">
                    <div>
                        <h2 class="text-base font-bold text-white flex items-center gap-2">
                            <span>Daftar Produk Upstream Supplier</span>
                            <span class="text-xs font-normal text-slate-400">({{ $provider->upstreamProducts->count() }} produk terdaftar)</span>
                        </h2>
                        <p class="text-xs text-slate-400 mt-0.5">Katalog data produk mentah langsung dari upstream supplier untuk memudahkan mapping ke SKU internal kita.</p>
                    </div>

                    <form action="{{ route('admin.providers.sync_products', $provider) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg shadow-indigo-600/30 transition-all">
                            <svg class="w-4 h-4 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            <span>Tarik / Sinkronkan Produk Upstream</span>
                        </button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-800 bg-slate-950/60 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                                <th class="py-3 px-3">SKU Upstream</th>
                                <th class="py-3 px-3">Nama Produk Supplier</th>
                                <th class="py-3 px-3">Kategori</th>
                                <th class="py-3 px-3 text-right">Modal / HPP Upstream</th>
                                <th class="py-3 px-3 text-center">Status</th>
                                <th class="py-3 px-3">Terakhir Sinkron</th>
                                <th class="py-3 px-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            @forelse($provider->upstreamProducts as $up)
                                <tr class="hover:bg-slate-800/40 transition-colors">
                                    <td class="py-3 px-3 font-mono font-bold text-indigo-400">{{ $up->supplier_sku }}</td>
                                    <td class="py-3 px-3 font-medium text-white">{{ $up->name }}</td>
                                    <td class="py-3 px-3 text-slate-400">{{ $up->category_name ?? '-' }}</td>
                                    <td class="py-3 px-3 text-right font-mono font-bold text-emerald-400">
                                        {{ $up->cost()->format() }}
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold {{ $up->status === 'active' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' }}">
                                            {{ strtoupper($up->status) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-slate-500 font-mono text-[11px]">
                                        {{ $up->last_synced_at?->format('d/m/Y H:i') ?? '-' }}
                                    </td>
                                    <td class="py-3 px-3 text-right">
                                        <a href="{{ route('admin.products.index') }}?search={{ $up->supplier_sku }}"
                                           class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-[11px] font-medium border border-slate-700">
                                            <span>Lihat di SKU Internal</span>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-12 text-center text-slate-500">
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <svg class="w-8 h-8 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                            <p class="text-sm">Belum ada katalog produk upstream yang tersinkronisasi.</p>
                                            <p class="text-xs text-slate-600">Klik tombol "Tarik / Sinkronkan Produk Upstream" di atas untuk mengambil data dari supplier.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
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

                    <button type="button"
                            @click="showAddErrorModal = true"
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg shadow-indigo-600/30 transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>+ Tambah Pemetaan Error Code</span>
                    </button>
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
                                        <span class="px-2 py-1 rounded text-[11px] {{ $em->internal_code === 'SUCCESS' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' }}">
                                            {{ $em->internal_code }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-slate-200">
                                        {{ $em->custom_message ?: ($standardErrors[$em->internal_code]['default_message'] ?? '-') }}
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold {{ $em->is_retryable ? 'bg-indigo-500/10 text-indigo-300 border border-indigo-500/20' : 'bg-slate-800 text-slate-400' }}">
                                            {{ $em->is_retryable ? 'Ya (Retryable)' : 'Tidak' }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-right">
                                        <form action="{{ route('admin.providers.error_mappings.destroy', ['provider' => $provider, 'mapping' => $em]) }}" method="POST"
                                              onsubmit="return confirm('Hapus pemetaan error code [{{ $em->upstream_code }}]?')" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 rounded-lg bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 border border-rose-500/20 transition-all">
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
                                    <option value="{{ $code }}">{{ $code }} &bull; {{ $def['label'] }}</option>
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
    </div>
</x-admin.layouts.app>
