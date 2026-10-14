<div class="bg-slate-800/80 backdrop-blur-md rounded-2xl border border-slate-700/60 p-5 sm:p-6 shadow-xl space-y-5"
     x-data="{
         baseCost: {{ $product->base_price_cents ? $product->base_price_cents->toRupiah() : 0 }},
         tierPrices: {
             @foreach($tiers as $tier)
                 '{{ $tier->id }}': {{ isset($tierPricesMap[$tier->id]) ? $tierPricesMap[$tier->id]->toRupiah() : ($product->base_price_cents ? $product->base_price_cents->toRupiah() : 0) }},
             @endforeach
         },
         calculateMargin(tierId) {
             const sell = parseFloat(this.tierPrices[tierId] || 0);
             return sell - this.baseCost;
         },
         calculateMarginPercent(tierId) {
             const margin = this.calculateMargin(tierId);
             if (this.baseCost <= 0) return 0;
             return ((margin / this.baseCost) * 100).toFixed(1);
         },
         applyGlobalMargin(marginAmount) {
             for (let key in this.tierPrices) {
                 this.tierPrices[key] = this.baseCost + parseFloat(marginAmount);
             }
         }
     }">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-700/60 pb-4">
        <div>
            <h2 class="text-base font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Matriks Tiering Harga & Margin</span>
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">
                Modal Provider: <span class="font-mono font-bold text-slate-200" x-text="'Rp ' + Number(baseCost).toLocaleString('id-ID')"></span>.
                Harga jual tidak boleh lebih rendah dari modal provider.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs text-slate-400">Quick Margin:</span>
            <button type="button" @click="applyGlobalMargin(200)" class="px-2.5 py-1 text-xs rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-200 transition-colors">+Rp 200</button>
            <button type="button" @click="applyGlobalMargin(500)" class="px-2.5 py-1 text-xs rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-200 transition-colors">+Rp 500</button>
            <button type="button" @click="applyGlobalMargin(1000)" class="px-2.5 py-1 text-xs rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-200 transition-colors">+Rp 1.000</button>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.products.prices.update', $product) }}" class="space-y-4">
        @csrf
        @method('PUT')

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-700/60 bg-slate-900/40 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                        <th class="py-3 px-3">Tier Pengguna</th>
                        <th class="py-3 px-3">Modal Upstream</th>
                        <th class="py-3 px-3">Harga Jual (Rp)</th>
                        <th class="py-3 px-3 text-right">Margin Bersih (Rp)</th>
                        <th class="py-3 px-3 text-right">Margin (%)</th>
                        <th class="py-3 px-3 text-center">Status Keamanan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    @forelse($tiers as $tier)
                        <tr class="hover:bg-slate-700/30">
                            {{-- Tier Name --}}
                            <td class="py-3 px-3">
                                <div class="font-semibold text-slate-200">{{ $tier->label ?? ucfirst($tier->name) }}</div>
                                <div class="text-[11px] text-slate-400 font-mono">{{ $tier->name }}</div>
                            </td>

                            {{-- Base Cost --}}
                            <td class="py-3 px-3 font-mono text-slate-400 whitespace-nowrap">
                                Rp <span x-text="Number(baseCost).toLocaleString('id-ID')"></span>
                            </td>

                            {{-- Sell Price Input --}}
                            <td class="py-3 px-3 whitespace-nowrap">
                                <div class="relative w-36">
                                    <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-slate-500 font-mono text-xs pointer-events-none">Rp</span>
                                    <input type="number"
                                           step="1"
                                           name="tier_prices[{{ $tier->id }}]"
                                           x-model="tierPrices['{{ $tier->id }}']"
                                           required
                                           class="w-full bg-slate-950/80 border border-slate-700 rounded-xl pl-8 pr-2.5 py-1.5 font-mono text-xs text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500">
                                </div>
                            </td>

                            {{-- Margin Nominal --}}
                            <td class="py-3 px-3 text-right font-mono font-semibold whitespace-nowrap"
                                :class="calculateMargin('{{ $tier->id }}') >= 0 ? 'text-emerald-400' : 'text-rose-400'">
                                <span x-text="calculateMargin('{{ $tier->id }}') >= 0 ? '+' : ''"></span>Rp <span x-text="Number(calculateMargin('{{ $tier->id }}')).toLocaleString('id-ID')"></span>
                            </td>

                            {{-- Margin % --}}
                            <td class="py-3 px-3 text-right font-mono whitespace-nowrap"
                                :class="calculateMargin('{{ $tier->id }}') >= 0 ? 'text-emerald-400' : 'text-rose-400'">
                                <span x-text="calculateMarginPercent('{{ $tier->id }}') + '%'"></span>
                            </td>

                            {{-- Margin Status --}}
                            <td class="py-3 px-3 text-center whitespace-nowrap">
                                <template x-if="calculateMargin('{{ $tier->id }}') >= 0">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        Profit Aman
                                    </span>
                                </template>
                                <template x-if="calculateMargin('{{ $tier->id }}') < 0">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                        Margin Negatif!
                                    </span>
                                </template>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-slate-500">
                                Belum ada user tier yang terdaftar di sistem.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-end pt-3 border-t border-slate-700/60">
            <button type="submit"
                    class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition-all flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>Simpan Matriks Harga Tier</span>
            </button>
        </div>
    </form>
</div>
