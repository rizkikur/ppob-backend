<div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-5 space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-sm font-bold text-white uppercase tracking-wider text-indigo-400 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                <span>Aturan Failover & Routing Mitra</span>
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">Konfigurasi supplier prioritas dan hak failover per kategori (ADR-004)</p>
        </div>

        <button type="button"
                @click="$dispatch('open-routing-modal')"
                class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl transition-all shadow-md shadow-indigo-600/30 flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            <span>Tambah Aturan</span>
        </button>
    </div>

    {{-- Rules Table --}}
    <div class="overflow-x-auto rounded-xl border border-slate-800">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-950/80 text-slate-400 font-semibold border-b border-slate-800">
                <tr>
                    <th class="px-3.5 py-2.5">Kategori</th>
                    <th class="px-3.5 py-2.5">Preferred Supplier</th>
                    <th class="px-3.5 py-2.5 text-center">Boleh Failover?</th>
                    <th class="px-3.5 py-2.5">Kebijakan Failover</th>
                    <th class="px-3.5 py-2.5 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 font-medium text-slate-300">
                @forelse($partner->routingRules as $rule)
                    <tr class="hover:bg-slate-800/30 transition-colors">
                        <td class="px-3.5 py-2.5">
                            @if($rule->category_code)
                                <span class="px-2 py-0.5 rounded-md bg-slate-800 border border-slate-700 text-slate-200 font-mono text-[11px]">
                                    {{ strtoupper($rule->category_code) }}
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded-md bg-indigo-500/10 border border-indigo-500/20 text-indigo-300 text-[11px] font-semibold">
                                    Semua Kategori (Global)
                                </span>
                            @endif
                        </td>
                        <td class="px-3.5 py-2.5">
                            @if($rule->preferredProvider)
                                <span class="text-white font-semibold">{{ $rule->preferredProvider->name }}</span>
                            @else
                                <span class="text-slate-400 italic">Otomatis (Prioritas Tertinggi)</span>
                            @endif
                        </td>
                        <td class="px-3.5 py-2.5 text-center">
                            @if($rule->allow_failover)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    Diizinkan
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                    Dilarang (Fast-fail)
                                </span>
                            @endif
                        </td>
                        <td class="px-3.5 py-2.5">
                            @php
                                $policyLabel = match($rule->failover_policy) {
                                    'none' => 'Tidak Ada (Tolak)',
                                    'same_category' => 'Kategori Sejenis',
                                    'any' => 'Semua Provider Kompatibel',
                                    default => $rule->failover_policy,
                                };
                            @endphp
                            <span class="text-xs text-slate-300">{{ $policyLabel }}</span>
                        </td>
                        <td class="px-3.5 py-2.5 text-right">
                            <form action="{{ route('admin.partners.routing_rules.destroy', [$partner, $rule]) }}"
                                  method="POST"
                                  class="inline"
                                  onsubmit="return confirm('Hapus aturan failover ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="p-1 text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-lg transition-colors"
                                        title="Hapus aturan">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3.5 py-6 text-center text-slate-500">
                            <p class="text-xs font-normal">Belum ada aturan khusus. Mitra ini menggunakan aturan default B2B: <strong class="text-slate-400">Failover Dilarang (Fast-fail HTTP 503)</strong>.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
