<div class="bg-slate-800/80 backdrop-blur-md rounded-2xl border border-slate-700/60 shadow-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="border-b border-slate-700/60 bg-slate-900/50 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                    <th class="py-3.5 px-3.5">ID & Waktu / SLA</th>
                    <th class="py-3.5 px-3.5">Pengguna & Tier</th>
                    <th class="py-3.5 px-3.5">Produk & Tujuan</th>
                    <th class="py-3.5 px-3.5">SN / Token PLN</th>
                    <th class="py-3.5 px-3.5 text-right">Finansial & Margin</th>
                    <th class="py-3.5 px-3.5">Provider & Routing</th>
                    <th class="py-3.5 px-3.5 text-center">Status</th>
                    <th class="py-3.5 px-3.5 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40 text-xs">
                @forelse($transactions as $trx)
                    @php
                        $sn = $trx->provider_response['sn'] ?? null;
                        $baseCost = $trx->amount_cents ? $trx->amount_cents->toRupiah() : 0;
                        $sellPrice = $trx->sell_price_cents ? $trx->sell_price_cents->toRupiah() : 0;
                        $margin = $sellPrice - $baseCost;
                        $marginPct = $baseCost > 0 ? round(($margin / $baseCost) * 100, 1) : 0;
                        $durationSeconds = $trx->updated_at && $trx->created_at ? max(0, $trx->updated_at->diffInSeconds($trx->created_at)) : 0;
                    @endphp
                    <tr class="hover:bg-slate-700/30 transition-colors group">
                        {{-- ID & Waktu / SLA --}}
                        <td class="py-3.5 px-3.5 whitespace-nowrap">
                            <div class="font-mono text-xs font-bold text-indigo-400">#{{ $trx->id }}</div>
                            <div class="text-[11px] text-slate-400">{{ $trx->created_at?->format('d M Y H:i:s') }}</div>
                            <div class="inline-flex items-center gap-1 mt-0.5 text-[10px] font-mono text-slate-500">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>SLA: {{ $durationSeconds }}s</span>
                            </div>
                        </td>

                        {{-- Pengguna & Tier --}}
                        <td class="py-3.5 px-3.5">
                            <div class="font-semibold text-slate-200 truncate max-w-[140px]">{{ $trx->user?->name ?? 'Guest/Unknown' }}</div>
                            <div class="font-mono text-[11px] text-slate-400 truncate max-w-[140px]">{{ $trx->user?->phone ?? '-' }}</div>
                            <div class="mt-0.5">
                                <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-medium bg-slate-900 text-slate-300 border border-slate-700/60">
                                    {{ $trx->user?->userTier?->label ?? $trx->user?->userTier?->name ?? 'Regular' }}
                                </span>
                            </div>
                        </td>

                        {{-- Produk & Tujuan --}}
                        <td class="py-3.5 px-3.5">
                            <div class="font-medium text-slate-200 truncate max-w-[160px]">{{ $trx->product?->name ?? 'Produk #'.$trx->product_id }}</div>
                            <div class="font-mono text-xs font-bold text-indigo-300 select-all">{{ $trx->customer_number }}</div>
                            <div class="text-[10px] text-slate-500 truncate max-w-[160px]">{{ $trx->product?->category?->name ?? '-' }}</div>
                        </td>

                        {{-- SN / Token PLN --}}
                        <td class="py-3.5 px-3.5">
                            @if($sn)
                                <div x-data="{ copied: false }" class="inline-flex items-center gap-1.5 p-1 rounded-lg bg-emerald-500/10 border border-emerald-500/20 max-w-[150px]">
                                    <span class="font-mono text-[11px] text-emerald-300 truncate select-all" title="{{ $sn }}">{{ $sn }}</span>
                                    <button type="button"
                                            @click="navigator.clipboard.writeText('{{ $sn }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                            class="p-0.5 text-emerald-400 hover:text-emerald-200 transition-colors"
                                            title="Salin SN/Token">
                                        <svg x-show="!copied" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                        <svg x-show="copied" x-cloak class="w-3 h-3 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                </div>
                            @else
                                <span class="text-slate-500 text-[11px] font-mono">-</span>
                            @endif
                        </td>

                        {{-- Finansial & Margin --}}
                        <td class="py-3.5 px-3.5 text-right whitespace-nowrap">
                            <div class="font-bold text-slate-100 font-mono">
                                {{ $trx->sell_price_cents ? $trx->sell_price_cents->format() : 'Rp 0' }}
                            </div>
                            <div class="text-[11px] text-slate-400 font-mono">
                                Modal: {{ $trx->amount_cents ? $trx->amount_cents->format() : 'Rp 0' }}
                            </div>
                            <div class="text-[10px] font-mono font-semibold {{ $margin >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                Margin: {{ $margin >= 0 ? '+' : '' }}Rp {{ number_format($margin, 0, ',', '.') }} ({{ $marginPct }}%)
                            </div>
                        </td>

                        {{-- Provider & Routing --}}
                        <td class="py-3.5 px-3.5 whitespace-nowrap">
                            <div class="font-semibold text-slate-200">
                                {{ $trx->product?->provider?->name ?? $trx->supplier?->name ?? '-' }}
                            </div>
                            <div class="font-mono text-[11px] text-slate-400 truncate max-w-[130px] select-all" title="{{ $trx->provider_ref }}">
                                {{ $trx->provider_ref ?? '-' }}
                            </div>
                            @if($trx->is_failover)
                                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.2 rounded text-[10px] font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20" title="Dialihkan via provider failover">
                                    ⚡ Failover
                                </span>
                            @endif
                        </td>

                        {{-- Status Badge --}}
                        <td class="py-3.5 px-3.5 text-center whitespace-nowrap">
                            @php
                                $badgeClass = match($trx->status) {
                                    'success' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                    'pending' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                    'processing' => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
                                    'failed' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                                    'refunded' => 'bg-purple-500/10 text-purple-400 border-purple-500/20',
                                    default => 'bg-slate-500/10 text-slate-400 border-slate-500/20',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold border {{ $badgeClass }}">
                                {{ ucfirst($trx->status) }}
                            </span>
                        </td>

                        {{-- Aksi --}}
                        <td class="py-3.5 px-3.5 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1.5">
                                {{-- Quick Requery Form Button --}}
                                @if(in_array($trx->status, ['pending', 'processing'], true))
                                    <form method="POST" action="{{ route('admin.transactions.requery', $trx) }}" class="inline">
                                        @csrf
                                        <button type="submit"
                                                class="p-1.5 rounded-lg bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 hover:text-indigo-300 border border-indigo-500/20 transition-all"
                                                title="Requery status sekarang">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                        </button>
                                    </form>
                                @endif

                                {{-- Detail Button --}}
                                <a href="{{ route('admin.transactions.show', $trx) }}"
                                   class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-slate-700/60 hover:bg-indigo-600 text-slate-200 hover:text-white rounded-xl text-xs font-semibold transition-all">
                                    <span>Detail</span>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-12 text-center text-slate-500">
                            Tidak ada transaksi yang cocok dengan kriteria filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Sleek FinTech Simple Pagination Footer (Zero COUNT(*) load) --}}
    @if($transactions->count() > 0)
        <div class="px-5 py-3.5 border-t border-slate-700/60 bg-slate-900/40 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
            <div class="text-slate-400 text-[11px] flex items-center gap-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-slate-800 text-slate-300 font-mono font-semibold border border-slate-700/60">
                    Hal. {{ $transactions->currentPage() }}
                </span>
                <span>Menampilkan {{ $transactions->count() }} data transaksi (Zero-Count O(1) Pagination)</span>
            </div>

            <div class="inline-flex items-center gap-2">
                {{-- Tombol Sebelumnya --}}
                @if($transactions->onFirstPage())
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800/40 text-slate-600 border border-slate-800/60 text-xs font-semibold cursor-not-allowed select-none">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        <span>Sebelumnya</span>
                    </span>
                @else
                    <a href="{{ $transactions->previousPageUrl() }}"
                       data-pagination-link
                       class="pagination-ajax-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700/80 text-xs font-semibold transition-all shadow-sm">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        <span>Sebelumnya</span>
                    </a>
                @endif

                {{-- Tombol Selanjutnya --}}
                @if($transactions->hasMorePages())
                    <a href="{{ $transactions->nextPageUrl() }}"
                       data-pagination-link
                       class="pagination-ajax-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700/80 text-xs font-semibold transition-all shadow-sm">
                        <span>Selanjutnya</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800/40 text-slate-600 border border-slate-800/60 text-xs font-semibold cursor-not-allowed select-none">
                        <span>Selanjutnya</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </span>
                @endif
            </div>
        </div>
    @endif
</div>
