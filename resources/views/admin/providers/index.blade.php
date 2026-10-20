<x-admin.layouts.app>
    <div class="space-y-6" x-data="{
        showToggleModal: false,
        selectedProviderForToggle: null,
        targetToggleStatus: false,
        toggleReason: '',
        showResetModal: false,
        selectedProviderForReset: null,
        resetReason: '',
        showConfigModal: false,
        selectedProviderForConfig: null,
        configThreshold: 5,
        configTimeout: 60
    }">

        {{-- Page Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-white">Manajemen Provider & Circuit Breaker</h1>
                <p class="mt-1 text-sm text-slate-400">Pantau ketersediaan upstream PPOB, kendalikan emergency toggle, dan reset Circuit Breaker counter.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 text-slate-300 text-xs font-mono">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    <span>ADR-003 Circuit Breaker Aktif</span>
                </span>
            </div>
        </div>

        {{-- Provider Cards / Table --}}
        <div class="bg-slate-800/80 backdrop-blur-md rounded-2xl border border-slate-700/60 shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-700/60 bg-slate-900/40 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                            <th class="py-3.5 px-4">Prioritas</th>
                            <th class="py-3.5 px-4">Provider & Kode</th>
                            <th class="py-3.5 px-4">Driver & Worker</th>
                            <th class="py-3.5 px-4 text-center">Status Operasional</th>
                            <th class="py-3.5 px-4 text-center">Circuit Breaker</th>
                            <th class="py-3.5 px-4 text-right">Intervensi Cepat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40 text-sm">
                        @forelse($providers as $prov)
                            <tr class="hover:bg-slate-700/30 transition-colors">
                                {{-- Priority --}}
                                <td class="py-3.5 px-4 whitespace-nowrap font-mono text-xs font-bold text-slate-300">
                                    #{{ $prov->priority }}
                                </td>

                                {{-- Provider Info --}}
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-100 text-sm flex items-center gap-2">
                                        <span>{{ $prov->name }}</span>
                                        <span class="font-mono text-[10px] px-2 py-0.5 rounded bg-slate-900 text-slate-300 border border-slate-700">
                                            {{ $prov->code }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-slate-400 mt-0.5">
                                        {{ $prov->products_count }} Produk SKU terhubung
                                    </div>
                                </td>

                                {{-- Driver --}}
                                <td class="py-3.5 px-4">
                                    <div class="font-mono text-xs text-indigo-300">{{ $prov->driver }}</div>
                                    <div class="text-[11px] text-slate-400">
                                        Queue: <span class="font-mono">{{ $prov->queue_name ?? 'default' }}</span>
                                        ({{ $prov->max_workers ?? 5 }} workers)
                                    </div>
                                </td>

                                {{-- Operational Status (Active/Maintenance/Disabled) --}}
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    @if($prov->is_active)
                                        <button type="button"
                                                @click="selectedProviderForToggle = { id: {{ $prov->id }}, name: '{{ addslashes($prov->name) }}' }; targetToggleStatus = false; toggleReason = ''; showToggleModal = true"
                                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 hover:bg-rose-500/10 hover:text-rose-400 hover:border-rose-500/20 transition-all cursor-pointer"
                                                title="Klik untuk menonaktifkan">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                            <span>Aktif (Online)</span>
                                        </button>
                                    @else
                                        <button type="button"
                                                @click="selectedProviderForToggle = { id: {{ $prov->id }}, name: '{{ addslashes($prov->name) }}' }; targetToggleStatus = true; toggleReason = ''; showToggleModal = true"
                                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20 hover:bg-emerald-500/10 hover:text-emerald-400 hover:border-emerald-500/20 transition-all cursor-pointer"
                                                title="Klik untuk mengaktifkan kembali">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                            <span>Nonaktif (Offline)</span>
                                        </button>
                                    @endif
                                </td>

                                {{-- Circuit Breaker Badge --}}
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    @php
                                        $cbState = $prov->circuit_state ?? 'CLOSED';
                                        $badgeStyle = match($cbState) {
                                            'OPEN' => 'bg-rose-500/20 text-rose-300 border-rose-500/30 animate-pulse',
                                            'HALF-OPEN' => 'bg-amber-500/20 text-amber-300 border-amber-500/30',
                                            default => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-bold border {{ $badgeStyle }}">
                                        @if($cbState === 'OPEN')
                                            ⚠️ TRIPPED (OPEN)
                                        @elseif($cbState === 'HALF-OPEN')
                                            ⚡ HALF-OPEN
                                        @else
                                            ✓ CLOSED (Normal)
                                        @endif
                                    </span>
                                </td>

                                {{-- Quick Actions --}}
                                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        {{-- Ping Health Check Tester --}}
                                        <div x-data="{ pinging: false, pingResult: null }">
                                            <button type="button"
                                                    @click="pinging = true; fetch('{{ route('admin.providers.ping', $prov) }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } }).then(r => r.json()).then(d => pingResult = d).finally(() => pinging = false)"
                                                    :disabled="pinging"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 text-xs font-semibold transition-all"
                                                    title="Uji koneksi dan ukur latensi respon provider">
                                                <svg :class="pinging ? 'animate-spin text-blue-400' : 'text-slate-400'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                                <span x-text="pinging ? 'Ping...' : (pingResult ? pingResult.latency_ms + 'ms' : 'Ping')">Ping</span>
                                            </button>
                                        </div>

                                        {{-- Config Breaker Threshold --}}
                                        <button type="button"
                                                @click="selectedProviderForConfig = { id: {{ $prov->id }}, name: '{{ addslashes($prov->name) }}' }; configThreshold = {{ $prov->config['circuit_breaker']['failure_threshold'] ?? 5 }}; configTimeout = {{ $prov->config['circuit_breaker']['cooldown_seconds'] ?? ($prov->timeout_seconds ?? 60) }}; showConfigModal = true"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 rounded-xl text-xs font-semibold transition-all"
                                                title="Ubah ambang batas failure threshold & timeout">
                                            <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                            <span>Config</span>
                                        </button>

                                        {{-- Reset Breaker Button --}}
                                        <button type="button"
                                                @click="selectedProviderForReset = { id: {{ $prov->id }}, code: '{{ $prov->code }}', name: '{{ addslashes($prov->name) }}' }; resetReason = ''; showResetModal = true"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 border border-amber-500/20 rounded-xl text-xs font-semibold transition-all"
                                                title="Reset counter kegagalan Circuit Breaker">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                            <span>Reset</span>
                                        </button>

                                        {{-- Detail Link --}}
                                        <a href="{{ route('admin.providers.show', $prov) }}"
                                           class="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-700/60 hover:bg-indigo-600 text-slate-200 hover:text-white rounded-xl text-xs font-semibold transition-all">
                                            <span>Detail</span>
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-12 text-center text-slate-500">Belum ada provider yang terdaftar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Incident Logs & Audit Trail Card --}}
        <div class="bg-slate-800/80 backdrop-blur-md rounded-2xl border border-slate-700/60 p-5 sm:p-6 shadow-xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-700/60 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-white uppercase tracking-wider">Log Riwayat Insiden & Intervensi Provider</h2>
                        <p class="text-[11px] text-slate-400">Audit trail reset breaker, toggle online/offline, dan pembaruan rute failover.</p>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-700/60 bg-slate-900/40 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                            <th class="py-2.5 px-3">Waktu</th>
                            <th class="py-2.5 px-3">Admin</th>
                            <th class="py-2.5 px-3">Tindakan / Action</th>
                            <th class="py-2.5 px-3">Catatan / Alasan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40">
                        @forelse($incidentLogs as $log)
                            <tr class="hover:bg-slate-700/30">
                                <td class="py-2.5 px-3 whitespace-nowrap font-mono text-[11px] text-slate-400">
                                    {{ $log->created_at?->format('d M Y H:i:s') }}
                                </td>
                                <td class="py-2.5 px-3 font-medium text-slate-200">
                                    {{ $log->admin?->name ?? 'System' }}
                                </td>
                                <td class="py-2.5 px-3">
                                    @php
                                        $badgeColor = match($log->action) {
                                            'provider.circuit_breaker_reset' => 'bg-amber-500/10 text-amber-300 border-amber-500/20',
                                            'provider.toggle_status' => 'bg-rose-500/10 text-rose-300 border-rose-500/20',
                                            'provider.circuit_breaker_config_update' => 'bg-blue-500/10 text-blue-300 border-blue-500/20',
                                            default => 'bg-slate-500/10 text-slate-300 border-slate-500/20',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-mono border {{ $badgeColor }}">
                                        {{ $log->action }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-slate-300">
                                    {{ $log->reason ?? '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-8 text-center text-slate-500 text-xs">
                                    Belum ada catatan insiden operasional provider.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Modal Emergency Toggle Provider Status --}}
        <div x-show="showToggleModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="showToggleModal = false"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-lg rounded-2xl bg-slate-900 border border-slate-800 p-6 shadow-2xl">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 flex items-center justify-center w-12 h-12 rounded-xl"
                             :class="targetToggleStatus ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-base font-bold text-white" x-text="targetToggleStatus ? 'Konfirmasi Pengaktifan Provider' : 'Emergency Nonaktifkan Provider'"></h3>
                            <p class="text-xs text-slate-400 mt-1">
                                Anda akan mengubah status operasional provider <span class="font-bold text-white" x-text="selectedProviderForToggle?.name"></span> menjadi
                                <span class="font-bold uppercase" :class="targetToggleStatus ? 'text-emerald-400' : 'text-rose-400'" x-text="targetToggleStatus ? 'AKTIF' : 'NONAKTIF'"></span>.
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-xs text-amber-300">
                        <p class="text-[11px] leading-relaxed">
                            ⚠️ Tindakan ini otomatis mengosongkan cache routing upstream. Jika dinonaktifkan, seluruh order transaksi baru akan dialihkan ke provider fallback cadangan.
                        </p>
                    </div>

                    <form :action="'{{ url('admin/providers') }}/' + selectedProviderForToggle?.id + '/toggle'" method="POST" class="mt-4 space-y-4">
                        @csrf
                        <input type="hidden" name="is_active" :value="targetToggleStatus ? '1' : '0'">

                        <div>
                            <label for="toggle_reason" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                                Alasan Perubahan Status <span class="text-rose-400">*</span>
                            </label>
                            <textarea id="toggle_reason"
                                      name="reason"
                                      x-model="toggleReason"
                                      rows="3"
                                      required
                                      placeholder="Sebutkan alasan operasional atau kendala upstream (minimal 10 karakter)..."
                                      class="w-full bg-slate-950/80 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                            <div class="flex items-center justify-between text-[11px] text-slate-500 mt-1">
                                <span>Minimal 10 karakter</span>
                                <span :class="toggleReason.length < 10 ? 'text-amber-400' : 'text-emerald-400'" x-text="toggleReason.length + ' / 255'"></span>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-2">
                            <button type="button" @click="showToggleModal = false" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-semibold">Batal</button>
                            <button type="submit"
                                    :disabled="toggleReason.trim().length < 10"
                                    class="px-4 py-2 rounded-xl text-white text-xs font-semibold shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                                    :class="targetToggleStatus ? 'bg-emerald-600 hover:bg-emerald-500 shadow-emerald-600/30' : 'bg-rose-600 hover:bg-rose-500 shadow-rose-600/30'">
                                <span x-text="targetToggleStatus ? 'Aktifkan Provider Sekarang' : 'Nonaktifkan Provider Sekarang'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Modal Reset Circuit Breaker --}}
        <div x-show="showResetModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="showResetModal = false"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-lg rounded-2xl bg-slate-900 border border-slate-800 p-6 shadow-2xl">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 flex items-center justify-center w-12 h-12 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-base font-bold text-white">Reset Counter Circuit Breaker</h3>
                            <p class="text-xs text-slate-400 mt-1">
                                Mereset counter kegagalan ke 0 dan mengembalikan status Circuit Breaker provider <span class="font-bold text-white" x-text="selectedProviderForReset?.name"></span> ke <span class="text-emerald-400 font-bold font-mono">CLOSED (Normal)</span>.
                            </p>
                        </div>
                    </div>

                    <form :action="'{{ url('admin/providers') }}/' + selectedProviderForReset?.id + '/circuit-breaker/reset'" method="POST" class="mt-5 space-y-4">
                        @csrf
                        <div>
                            <label for="reset_reason" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                                Catatan / Alasan Reset
                            </label>
                            <input type="text"
                                   id="reset_reason"
                                   name="reason"
                                   x-model="resetReason"
                                   placeholder="Contoh: Upstream sudah pulih dari maintenance..."
                                   class="w-full bg-slate-950/80 border border-slate-700/80 rounded-xl px-3.5 py-2 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500">
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-2">
                            <button type="button" @click="showResetModal = false" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-semibold">Batal</button>
                            <button type="submit"
                                    class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-semibold shadow-lg shadow-amber-600/30 transition-all">
                                Eksekusi Reset Breaker
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Modal Konfigurasi Circuit Breaker Dinamis --}}
        <div x-show="showConfigModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="showConfigModal = false"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-lg rounded-2xl bg-slate-900 border border-slate-800 p-6 shadow-2xl space-y-4">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 flex items-center justify-center w-12 h-12 rounded-xl bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-base font-bold text-white">Konfigurasi Circuit Breaker</h3>
                            <p class="text-xs text-slate-400 mt-1">
                                Atur ambang batas kegagalan dan durasi cooldown untuk provider <span class="font-bold text-white" x-text="selectedProviderForConfig?.name"></span>.
                            </p>
                        </div>
                    </div>

                    <form :action="'{{ url('admin/providers') }}/' + selectedProviderForConfig?.id + '/circuit-breaker/config'" method="POST" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-2 gap-3.5">
                            <div>
                                <label for="failure_threshold" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                                    Batas Kegagalan (Trip)
                                </label>
                                <input type="number"
                                       id="failure_threshold"
                                       name="failure_threshold"
                                       x-model="configThreshold"
                                       min="1"
                                       max="50"
                                       required
                                       class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-slate-100 font-mono focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <span class="text-[10px] text-slate-400 mt-1 block">Default: 5 kali gagal berturut-turut</span>
                            </div>

                            <div>
                                <label for="cooldown_seconds" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                                    Cooldown Timeout (Detik)
                                </label>
                                <input type="number"
                                       id="cooldown_seconds"
                                       name="cooldown_seconds"
                                       x-model="configTimeout"
                                       min="10"
                                       max="600"
                                       required
                                       class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-slate-100 font-mono focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <span class="text-[10px] text-slate-400 mt-1 block">Durasi sebelum uji coba HALF-OPEN</span>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-xl bg-slate-950/70 border border-slate-800 text-xs text-slate-400 space-y-1">
                            <p class="text-[11px]"><strong class="text-slate-200">Keterangan:</strong> Saat kegagalan mencapai batas, circuit langsung OPEN (fail-fast) dan seluruh transaksi diarahkan ke rute failover selama masa cooldown.</p>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-800">
                            <button type="button" @click="showConfigModal = false" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-semibold">Batal</button>
                            <button type="submit"
                                    class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition-all">
                                Simpan Konfigurasi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-admin.layouts.app>
