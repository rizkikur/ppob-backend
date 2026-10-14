<x-admin.layouts.app>
    <div class="space-y-6 max-w-5xl mx-auto" x-data="{ showResetModal: false, showToggleModal: false, toggleReason: '', resetReason: '' }">
        {{-- Flash Alerts --}}
        @if(session('success'))
            <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-center justify-between">
                <span>{{ session('success') }}</span>
                <button type="button" @click="$el.parentElement.remove()" class="text-emerald-400/70 hover:text-emerald-400">✕</button>
            </div>
        @endif

        {{-- Top Navigation --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.providers.index') }}"
                   class="p-2 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700/60 text-slate-400 hover:text-white transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-bold tracking-tight text-white">{{ $provider->name }}</h1>
                        <span class="font-mono text-xs px-2.5 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-bold">
                            {{ $provider->code }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Driver: <span class="font-mono text-slate-300">{{ $provider->driver }}</span> | Prioritas: #{{ $provider->priority }}</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
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

                <button type="button"
                        @click="showResetModal = true"
                        class="px-3.5 py-2 rounded-xl bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 border border-amber-500/20 text-xs font-semibold transition-all">
                    Reset Circuit Breaker
                </button>
                <button type="button"
                        @click="showToggleModal = true"
                        class="px-3.5 py-2 rounded-xl text-xs font-semibold border transition-all {{ $provider->is_active ? 'bg-rose-500/10 text-rose-400 border-rose-500/20 hover:bg-rose-500/20' : 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20 hover:bg-emerald-500/20' }}">
                    {{ $provider->is_active ? 'Nonaktifkan Provider' : 'Aktifkan Provider' }}
                </button>
            </div>
        </div>

        {{-- Cards Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- Circuit Breaker Card --}}
            <div class="bg-slate-800/80 backdrop-blur-md rounded-2xl border border-slate-700/60 p-5 shadow-xl space-y-4">
                <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Status Circuit Breaker</h2>
                <div>
                    @php
                        $cbState = $provider->circuit_state ?? 'CLOSED';
                    @endphp
                    <div class="text-2xl font-mono font-bold {{ $cbState === 'OPEN' ? 'text-rose-400' : ($cbState === 'HALF-OPEN' ? 'text-amber-400' : 'text-emerald-400') }}">
                        {{ $cbState }}
                    </div>
                    <p class="text-xs text-slate-400 mt-1">
                        {{ $cbState === 'OPEN' ? 'Traffic dialihkan fail-fast karena error rate tinggi.' : ($cbState === 'HALF-OPEN' ? 'Masa cooldown selesai, uji coba 1 request.' : 'Operasional normal, melayani seluruh request transaksi.') }}
                    </p>
                </div>
            </div>

            {{-- Worker & Queue Card --}}
            <div class="bg-slate-800/80 backdrop-blur-md rounded-2xl border border-slate-700/60 p-5 shadow-xl space-y-4">
                <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Infrastruktur Queue</h2>
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between py-1 border-b border-slate-700/40">
                        <span class="text-slate-400">Queue Name</span>
                        <span class="font-mono text-slate-200">{{ $provider->queue_name ?? 'default' }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-700/40">
                        <span class="text-slate-400">Max Workers</span>
                        <span class="font-mono text-slate-200">{{ $provider->max_workers ?? 5 }}</span>
                    </div>
                    <div class="flex justify-between py-1">
                        <span class="text-slate-400">Rate Limit</span>
                        <span class="font-mono text-slate-200">{{ $provider->rate_limit_per_minute ?? 60 }}/mnt</span>
                    </div>
                </div>
            </div>

            {{-- Operational Parameters --}}
            <div class="bg-slate-800/80 backdrop-blur-md rounded-2xl border border-slate-700/60 p-5 shadow-xl space-y-4">
                <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Parameter Operasional</h2>
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between py-1 border-b border-slate-700/40">
                        <span class="text-slate-400">Status Aktif</span>
                        <span class="font-semibold {{ $provider->is_active ? 'text-emerald-400' : 'text-rose-400' }}">
                            {{ $provider->is_active ? 'Online' : 'Offline' }}
                        </span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-700/40">
                        <span class="text-slate-400">Timeout Request</span>
                        <span class="font-mono text-slate-200">{{ $provider->timeout_seconds ?? 30 }} detik</span>
                    </div>
                    <div class="flex justify-between py-1">
                        <span class="text-slate-400">Balance API Support</span>
                        <span class="text-slate-200">{{ $provider->supports_balance_api ? 'Ya' : 'Tidak' }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Products Handled Table --}}
        <div class="bg-slate-800/80 backdrop-blur-md rounded-2xl border border-slate-700/60 p-5 sm:p-6 shadow-xl space-y-4">
            <h2 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                <span>Produk SKU Yang Terhubung ({{ $provider->products->count() }})</span>
            </h2>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-700/60 bg-slate-900/40 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                            <th class="py-2.5 px-3">Kode SKU</th>
                            <th class="py-2.5 px-3">Nama Produk</th>
                            <th class="py-2.5 px-3">Kategori</th>
                            <th class="py-2.5 px-3 text-right">Modal Provider</th>
                            <th class="py-2.5 px-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40">
                        @forelse($provider->products as $p)
                            <tr class="hover:bg-slate-700/30">
                                <td class="py-2.5 px-3 font-mono font-bold text-indigo-400">{{ $p->sku_code }}</td>
                                <td class="py-2.5 px-3 font-medium text-slate-200">{{ $p->name }}</td>
                                <td class="py-2.5 px-3 text-slate-400">{{ $p->category?->name ?? '-' }}</td>
                                <td class="py-2.5 px-3 text-right font-mono text-slate-200">
                                    {{ $p->base_price_cents ? $p->base_price_cents->format() : 'Rp 0' }}
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold {{ $p->is_active ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' }}">
                                        {{ $p->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-slate-500">Belum ada produk SKU yang dipetakan ke provider ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Grid: Circuit Breaker Config & Incident Logs --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Circuit Breaker Threshold Settings Card --}}
            <div class="bg-slate-800/80 backdrop-blur-md rounded-2xl border border-slate-700/60 p-5 sm:p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between border-b border-slate-700/60 pb-3">
                    <h2 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span>Konfigurasi Ambang Batas Breaker</span>
                    </h2>
                </div>

                <form method="POST" action="{{ route('admin.providers.circuit_breaker.config', $provider) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-2 gap-3.5">
                        <div>
                            <label for="show_failure_threshold" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                                Batas Gagal (Trip)
                            </label>
                            <input type="number"
                                   id="show_failure_threshold"
                                   name="failure_threshold"
                                   value="{{ $provider->config['circuit_breaker']['failure_threshold'] ?? 5 }}"
                                   min="1"
                                   max="50"
                                   required
                                   class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 font-mono focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label for="show_cooldown_seconds" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                                Cooldown Timeout (Detik)
                            </label>
                            <input type="number"
                                   id="show_cooldown_seconds"
                                   name="cooldown_seconds"
                                   value="{{ $provider->config['circuit_breaker']['cooldown_seconds'] ?? ($provider->timeout_seconds ?? 60) }}"
                                   min="10"
                                   max="600"
                                   required
                                   class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 font-mono focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div class="flex items-center justify-end pt-2">
                        <button type="submit"
                                class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition-all">
                            Simpan Ambang Batas
                        </button>
                    </div>
                </form>
            </div>

            {{-- Incident & Activity Logs Card --}}
            <div class="bg-slate-800/80 backdrop-blur-md rounded-2xl border border-slate-700/60 p-5 sm:p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between border-b border-slate-700/60 pb-3">
                    <h2 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Log Insiden & Intervensi</span>
                    </h2>
                </div>

                <div class="overflow-x-auto max-h-[190px] overflow-y-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <tbody class="divide-y divide-slate-700/40">
                            @forelse($incidentLogs as $log)
                                <tr class="hover:bg-slate-700/30">
                                    <td class="py-2 px-2 text-[10px] text-slate-400 font-mono whitespace-nowrap">
                                        {{ $log->created_at?->format('d/m H:i') }}
                                    </td>
                                    <td class="py-2 px-2 font-mono text-[10px] text-amber-300 whitespace-nowrap">
                                        {{ $log->action }}
                                    </td>
                                    <td class="py-2 px-2 text-slate-300 text-[11px] truncate max-w-[180px]" title="{{ $log->reason }}">
                                        {{ $log->reason ?? '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-6 text-center text-slate-500 text-xs">
                                        Belum ada riwayat insiden untuk provider ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
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
