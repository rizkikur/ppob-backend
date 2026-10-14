<x-admin.layouts.app>
    @php
        $sn = $transaction->provider_response['sn'] ?? null;
        $rc = $transaction->provider_response['rc'] ?? null;
        $providerMsg = $transaction->provider_response['message'] ?? $transaction->failure_reason ?? null;
        $baseCost = $transaction->amount_cents ? $transaction->amount_cents->toRupiah() : 0;
        $sellPrice = $transaction->sell_price_cents ? $transaction->sell_price_cents->toRupiah() : 0;
        $margin = $sellPrice - $baseCost;
        $marginPct = $baseCost > 0 ? round(($margin / $baseCost) * 100, 1) : 0;
        $slaSeconds = $transaction->updated_at && $transaction->created_at ? max(0, $transaction->updated_at->diffInSeconds($transaction->created_at)) : 0;
    @endphp

    <div x-data="{ showRequeryModal: false, showRefundModal: false, showOverrideModal: false, showWebhookRetryModal: false, showReceiptModal: false, receiptTab: 'thermal', copiedWa: false }" class="space-y-6">
        {{-- Flash Alerts --}}
        @if(session('success'))
            <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>{{ session('success') }}</span>
                </div>
                <button type="button" @click="$el.parentElement.remove()" class="text-emerald-400/70 hover:text-emerald-400">✕</button>
            </div>
        @endif

        @if(session('error'))
            <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span>{{ session('error') }}</span>
                </div>
                <button type="button" @click="$el.parentElement.remove()" class="text-rose-400/70 hover:text-rose-400">✕</button>
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

        {{-- Top Navigation & Action Bar --}}
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.transactions.index') }}"
                   class="p-2 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700/60 text-slate-400 hover:text-white transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-2xl font-bold tracking-tight text-white">Transaksi #{{ $transaction->id }}</h1>
                        @php
                            $badgeClass = match($transaction->status) {
                                'success' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                'pending' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                'processing' => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
                                'failed' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                                'refunded' => 'bg-purple-500/10 text-purple-400 border-purple-500/20',
                                default => 'bg-slate-500/10 text-slate-400 border-slate-500/20',
                            };
                        @endphp
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border {{ $badgeClass }}">
                            {{ strtoupper($transaction->status) }}
                        </span>
                        <span class="text-xs text-slate-400 font-mono">
                            SLA: <strong class="text-slate-200">{{ $slaSeconds }}s</strong>
                        </span>
                    </div>
                    <p class="text-xs text-slate-400 mt-0.5">Dibuat: {{ $transaction->created_at?->format('d M Y, H:i:s T') }} | Update: {{ $transaction->updated_at?->format('d M Y, H:i:s T') }}</p>
                </div>
            </div>

            {{-- Intervention Buttons --}}
            <div class="flex flex-wrap items-center gap-2">
                {{-- Cetak Struk / Salin Format WA --}}
                <button type="button"
                        @click="showReceiptModal = true"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Cetak Struk / Salin WA</span>
                </button>

                {{-- Requery --}}
                <button type="button"
                        @click="showRequeryModal = true"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-semibold transition-all">
                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>Re-query Upstream</span>
                </button>

                {{-- Status Override --}}
                <button type="button"
                        @click="showOverrideModal = true"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-amber-600/20 hover:bg-amber-600/30 text-amber-300 border border-amber-500/30 text-xs font-semibold transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    <span>Status Override</span>
                </button>

                {{-- Manual Refund --}}
                @if(in_array($transaction->status, ['failed', 'processing', 'pending'], true))
                    <button type="button"
                            @click="showRefundModal = true"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold shadow-lg shadow-purple-600/30 transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        <span>Manual Refund</span>
                    </button>
                @endif

                {{-- Retry Webhook (If B2B Partner) --}}
                @if($transaction->partner)
                    <form method="POST" action="{{ route('admin.transactions.webhook.retry', $transaction) }}" class="inline">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-blue-600/20 hover:bg-blue-600/30 text-blue-300 border border-blue-500/30 text-xs font-semibold transition-all"
                                title="Kirim ulang webhook callback ke server mitra">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            <span>Retry Webhook B2B</span>
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- HERO SECTION: Output Produk / Serial Number / Token PLN --}}
        @if($sn)
            <div class="bg-gradient-to-r from-emerald-950/60 via-slate-900 to-indigo-950/60 border border-emerald-500/30 rounded-2xl p-5 shadow-2xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
                 x-data="{ copied: false }">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center flex-shrink-0 text-emerald-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <span class="text-xs font-semibold uppercase tracking-wider text-emerald-400">Serial Number (SN) / Token PLN Produk</span>
                        <div class="text-xl sm:text-2xl font-mono font-bold text-white tracking-wider select-all mt-0.5">{{ $sn }}</div>
                        <p class="text-[11px] text-slate-400 mt-0.5">Berikan nomor token/SN ini kepada pelanggan sebagai bukti sah keberhasilan pengisian.</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button"
                            @click="showReceiptModal = true; receiptTab = 'thermal'"
                            class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white text-xs font-semibold border border-slate-700 transition-all">
                        <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        <span>Cetak Struk</span>
                    </button>
                    <button type="button"
                            @click="navigator.clipboard.writeText('{{ $sn }}'); copied = true; setTimeout(() => copied = false, 2000)"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold shadow-lg shadow-emerald-600/30 transition-all font-mono">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        <span x-text="copied ? 'Berhasil Tersalin!' : 'Salin Token / SN'">Salin Token / SN</span>
                    </button>
                </div>
            </div>
        @endif

        {{-- Main Layout Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Left Column: Diagnostic Cards --}}
            <div class="lg:col-span-1 space-y-6">
                {{-- Financial & Margin Card --}}
                <div class="bg-slate-800/80 backdrop-blur-md rounded-2xl border border-slate-700/60 p-5 shadow-xl space-y-3.5">
                    <h2 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2 border-b border-slate-700/60 pb-3">
                        <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Rincian Finansial & Margin</span>
                    </h2>

                    <div class="space-y-2.5 text-xs">
                        <div class="flex justify-between items-center py-1 border-b border-slate-700/40">
                            <span class="text-slate-400">Harga Jual (User)</span>
                            <span class="font-bold text-white font-mono text-sm">
                                {{ $transaction->sell_price_cents ? $transaction->sell_price_cents->format() : 'Rp 0' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center py-1 border-b border-slate-700/40">
                            <span class="text-slate-400">Modal Provider (HPP)</span>
                            <span class="font-semibold text-slate-300 font-mono">
                                {{ $transaction->amount_cents ? $transaction->amount_cents->format() : 'Rp 0' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center py-1 border-b border-slate-700/40">
                            <span class="text-slate-400">Margin Laba Kotor</span>
                            <span class="font-bold font-mono {{ $margin >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                {{ $margin >= 0 ? '+' : '' }}Rp {{ number_format($margin, 0, ',', '.') }} ({{ $marginPct }}%)
                            </span>
                        </div>
                        <div class="flex justify-between items-center py-1 border-b border-slate-700/40">
                            <span class="text-slate-400">Metode Pembayaran</span>
                            <span class="text-slate-200 font-medium">Dompet Saldo Internal</span>
                        </div>
                        <div class="flex justify-between items-center py-1 border-b border-slate-700/40">
                            <span class="text-slate-400">Idempotency Key</span>
                            <span class="font-mono text-[11px] text-slate-300 truncate max-w-[150px] select-all" title="{{ $transaction->idempotency_key }}">
                                {{ $transaction->idempotency_key }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center py-1">
                            <span class="text-slate-400">Jalur Eksekusi</span>
                            <span class="font-semibold {{ $transaction->is_failover ? 'text-amber-400' : 'text-slate-200' }}">
                                {{ $transaction->is_failover ? 'Failover Cadangan' : 'Primary Route' }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- User & Customer Info Card --}}
                <div class="bg-slate-800/80 backdrop-blur-md rounded-2xl border border-slate-700/60 p-5 shadow-xl space-y-3.5">
                    <h2 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2 border-b border-slate-700/60 pb-3">
                        <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <span>Data Pengguna & Pelanggan</span>
                    </h2>

                    <div class="space-y-2.5 text-xs">
                        <div class="flex justify-between items-center py-1 border-b border-slate-700/40">
                            <span class="text-slate-400">Nama Pengguna</span>
                            <span class="font-semibold text-white">{{ $transaction->user?->name ?? 'Guest/Unknown' }}</span>
                        </div>
                        <div class="flex justify-between items-center py-1 border-b border-slate-700/40">
                            <span class="text-slate-400">Nomor HP Terdaftar</span>
                            <span class="font-mono text-slate-300 select-all">{{ $transaction->user?->phone ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center py-1 border-b border-slate-700/40">
                            <span class="text-slate-400">Tier Pengguna</span>
                            <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-900 border border-slate-700 text-slate-200">
                                {{ $transaction->user?->userTier?->label ?? $transaction->user?->userTier?->name ?? 'Regular' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center py-1 border-b border-slate-700/40">
                            <span class="text-slate-400">Status KYC Akun</span>
                            <span class="font-semibold {{ $transaction->user?->is_verified ? 'text-emerald-400' : 'text-slate-400' }}">
                                {{ $transaction->user?->is_verified ? 'Terverifikasi' : 'Belum Verifikasi' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center py-1 border-b border-slate-700/40">
                            <span class="text-slate-400">Saldo Dompet Terkini</span>
                            <span class="font-mono font-bold text-emerald-400 text-sm">
                                {{ $transaction->user?->wallet?->balance_cents ? $transaction->user->wallet->balance_cents->format() : 'Rp 0' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center py-1">
                            <span class="text-slate-400">Nomor Tujuan / Pelanggan</span>
                            <span class="font-mono font-bold text-indigo-400 text-sm select-all">{{ $transaction->customer_number }}</span>
                        </div>
                    </div>
                </div>

                {{-- Product & Upstream Card --}}
                <div class="bg-slate-800/80 backdrop-blur-md rounded-2xl border border-slate-700/60 p-5 shadow-xl space-y-3.5">
                    <h2 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2 border-b border-slate-700/60 pb-3">
                        <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        <span>Produk & Upstream Driver</span>
                    </h2>

                    <div class="space-y-2.5 text-xs">
                        <div class="flex justify-between items-center py-1 border-b border-slate-700/40">
                            <span class="text-slate-400">Produk</span>
                            <span class="font-semibold text-slate-200">{{ $transaction->product?->name ?? 'SKU #'.$transaction->product_id }}</span>
                        </div>
                        <div class="flex justify-between items-center py-1 border-b border-slate-700/40">
                            <span class="text-slate-400">Kode SKU</span>
                            <span class="font-mono text-slate-300 font-bold">{{ $transaction->product?->sku_code ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center py-1 border-b border-slate-700/40">
                            <span class="text-slate-400">Kategori / Tipe</span>
                            <span class="text-slate-300">{{ $transaction->product?->category?->name ?? '-' }} ({{ strtoupper($transaction->product?->product_type ?? 'prepaid') }})</span>
                        </div>
                        <div class="flex justify-between items-center py-1 border-b border-slate-700/40">
                            <span class="text-slate-400">Provider Eksekusi</span>
                            <span class="font-semibold text-slate-200">
                                {{ $transaction->supplier?->name ?? $transaction->product?->provider?->name ?? '-' }}
                            </span>
                        </div>
                        @if($transaction->is_failover && $transaction->originalSupplier)
                            <div class="flex justify-between items-center py-1 border-b border-slate-700/40">
                                <span class="text-slate-400">Provider Awal (Failover)</span>
                                <span class="text-amber-400 line-through">{{ $transaction->originalSupplier->name }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between items-center py-1 border-b border-slate-700/40">
                            <span class="text-slate-400">Provider Ref ID</span>
                            <span class="font-mono text-[11px] text-slate-300 select-all">{{ $transaction->provider_ref ?? '-' }}</span>
                        </div>
                        @if($rc)
                            <div class="flex justify-between items-center py-1">
                                <span class="text-slate-400">Response Code Upstream</span>
                                <span class="font-mono font-bold px-2 py-0.5 rounded bg-slate-900 border border-slate-700 {{ $rc === '00' ? 'text-emerald-400' : 'text-amber-400' }}">
                                    RC: {{ $rc }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Inquiry Details Card (If Postpaid / Bill Inquiry exists) --}}
                @if($transaction->inquiry)
                    <div class="bg-slate-800/80 backdrop-blur-md rounded-2xl border border-indigo-500/30 p-5 shadow-xl space-y-3.5">
                        <h2 class="text-sm font-bold text-indigo-300 uppercase tracking-wider flex items-center gap-2 border-b border-slate-700/60 pb-3">
                            <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span>Data Tagihan / Inquiry</span>
                        </h2>

                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between items-center py-1 border-b border-slate-700/40">
                                <span class="text-slate-400">Nama Pelanggan (Bill)</span>
                                <span class="font-semibold text-white">{{ $transaction->inquiry->provider_response['customer_name'] ?? $transaction->inquiry->customer_number }}</span>
                            </div>
                            <div class="flex justify-between items-center py-1 border-b border-slate-700/40">
                                <span class="text-slate-400">Inquiry Ref</span>
                                <span class="font-mono text-slate-300">{{ $transaction->inquiry->inquiry_ref ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center py-1 border-b border-slate-700/40">
                                <span class="text-slate-400">Periode Tagihan</span>
                                <span class="font-mono text-slate-300">{{ $transaction->inquiry->provider_response['period'] ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center py-1 border-b border-slate-700/40">
                                <span class="text-slate-400">Nominal Pokok</span>
                                <span class="font-mono text-slate-300">Rp {{ number_format($transaction->inquiry->amount_cents / 100, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between items-center py-1">
                                <span class="text-slate-400">Admin Fee Tagihan</span>
                                <span class="font-mono text-slate-300">Rp {{ number_format($transaction->inquiry->admin_fee_cents / 100, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Right Column: Lifecycle, Webhook Deliveries, Ledger, Raw JSON --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- B2B Webhook Deliveries Card (If Partner Transaction) --}}
                @if($transaction->partner)
                    <div class="bg-slate-800/80 backdrop-blur-md rounded-2xl border border-blue-500/30 p-5 shadow-xl space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-700/60 pb-3">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                <h2 class="text-sm font-bold text-white uppercase tracking-wider">Status Webhook Callback Mitra B2B</h2>
                            </div>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded bg-blue-500/10 text-blue-300 border border-blue-500/20">
                                Mitra: {{ $transaction->partner->name }}
                            </span>
                        </div>

                        @if($transaction->webhookDeliveries->isNotEmpty())
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="border-b border-slate-700/60 bg-slate-900/40 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                                            <th class="py-2.5 px-3">Attempt</th>
                                            <th class="py-2.5 px-3">Status Callback</th>
                                            <th class="py-2.5 px-3">HTTP Code</th>
                                            <th class="py-2.5 px-3">Waktu Eksekusi</th>
                                            <th class="py-2.5 px-3">Next Retry</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-700/40">
                                        @foreach($transaction->webhookDeliveries as $wh)
                                            <tr class="hover:bg-slate-700/30">
                                                <td class="py-2.5 px-3 font-mono font-bold text-slate-300">#{{ $wh->attempt }} / 5</td>
                                                <td class="py-2.5 px-3">
                                                    @if($wh->isDelivered())
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Delivered</span>
                                                    @elseif($wh->isFailedPermanent())
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">Failed Permanent</span>
                                                    @else
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">{{ ucfirst($wh->status) }}</span>
                                                    @endif
                                                </td>
                                                <td class="py-2.5 px-3 font-mono text-slate-200">
                                                    {{ $wh->response_code ?? '-' }}
                                                </td>
                                                <td class="py-2.5 px-3 text-slate-400 font-mono">
                                                    {{ $wh->delivered_at?->format('H:i:s') ?? $wh->updated_at?->format('H:i:s') }}
                                                </td>
                                                <td class="py-2.5 px-3 text-slate-400 font-mono">
                                                    {{ $wh->next_retry_at?->format('H:i:s') ?? '-' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="py-4 text-center text-slate-400 text-xs">
                                Webhook delivery belum di-dispatch untuk transaksi ini.
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Lifecycle Timeline & Audit Trail --}}
                @include('admin.transactions.components._lifecycle_timeline')

                {{-- Upstream Provider Masked Payload --}}
                @include('admin.transactions.components._provider_payload_viewer')

                {{-- Related Wallet Mutations --}}
                @include('admin.transactions.components._related_wallet_mutations')
            </div>
        </div>

        {{-- Modals --}}
        @include('admin.transactions.components._requery_confirm_modal')
        @include('admin.transactions.components._refund_modal')
        @include('admin.transactions.components._override_modal')
        @include('admin.transactions.components._receipt_modal')
    </div>

    {{-- Thermal Print Styling --}}
    <style>
        @media print {
            body {
                background: white !important;
                color: black !important;
            }
            body * {
                visibility: hidden !important;
            }
            #thermal-receipt-print-area, #thermal-receipt-print-area * {
                visibility: visible !important;
            }
            #thermal-receipt-print-area {
                position: fixed !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                max-width: 80mm !important;
                margin: 0 auto !important;
                padding: 5px !important;
                box-shadow: none !important;
                border: none !important;
                background: white !important;
                color: black !important;
            }
        }
    </style>
</x-admin.layouts.app>
