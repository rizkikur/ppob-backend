{{-- Custom Pricing Component --}}
<div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-6">
    <div class="flex items-center justify-between mb-4 pb-4 border-b border-slate-800">
        <div>
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                <span>Harga Khusus Produk (Custom Pricing)</span>
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">Override harga jual tier default khusus untuk mitra ini (dengan proteksi anti-minus margin)</p>
        </div>

        <button type="button"
                @click="$dispatch('open-pricing-modal', {})"
                class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-xs rounded-xl transition-all shadow-md shadow-indigo-600/20 flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            <span>Tambah Harga Khusus</span>
        </button>
    </div>

    @if($partner->productPrices->isEmpty())
        <div class="p-8 text-center bg-slate-950/40 rounded-xl border border-slate-800/80">
            <p class="text-xs text-slate-400 font-medium">Mitra ini menggunakan harga default tier (tidak ada harga khusus custom).</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 uppercase tracking-wider font-semibold">
                        <th class="py-2.5 px-3">Produk / SKU</th>
                        <th class="py-2.5 px-3">HPP Modal</th>
                        <th class="py-2.5 px-3">Harga Khusus Mitra</th>
                        <th class="py-2.5 px-3">Margin Laba</th>
                        <th class="py-2.5 px-3">Status</th>
                        <th class="py-2.5 px-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-slate-300">
                    @foreach($partner->productPrices as $customPrice)
                        @php
                            $prod = $customPrice->product;
                            $baseCents = $prod?->base_price_cents?->toCents() ?? 0;
                            $sellCents = $customPrice->sell_price_cents?->toCents() ?? 0;
                            $marginCents = $sellCents - $baseCents;
                        @endphp
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="py-2.5 px-3">
                                <div class="font-bold text-white">{{ $prod?->name ?? 'SKU Tidak Dikenal' }}</div>
                                <div class="font-mono text-[11px] text-indigo-400">{{ $prod?->sku_code ?? '-' }}</div>
                            </td>
                            <td class="py-2.5 px-3 font-mono text-slate-400">
                                Rp {{ number_format($baseCents / 100, 0, ',', '.') }}
                            </td>
                            <td class="py-2.5 px-3 font-mono font-bold text-white">
                                Rp {{ number_format($sellCents / 100, 0, ',', '.') }}
                            </td>
                            <td class="py-2.5 px-3 font-mono">
                                <span class="{{ $marginCents >= 0 ? 'text-emerald-400' : 'text-rose-400 font-bold' }}">
                                    Rp {{ number_format($marginCents / 100, 0, ',', '.') }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium {{ $customPrice->is_active ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-700 text-slate-400' }}">
                                    {{ $customPrice->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                <button type="button"
                                        @click="$dispatch('open-pricing-modal', { productId: '{{ $prod?->id }}', sellPrice: '{{ $sellCents / 100 }}' })"
                                        class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-xs font-medium transition-colors">
                                    Edit
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
