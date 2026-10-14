<div class="bg-slate-800/80 backdrop-blur-md rounded-2xl border border-slate-700/60 p-5 sm:p-6 shadow-xl space-y-4"
     x-data="{
        routes: {{ Js::from($product->supplierRoutes->map(fn($r) => ['provider_id' => $r->provider_id, 'priority' => $r->priority, 'is_active' => (bool)$r->is_active])->values()) }},
        addRoute() {
            this.routes.push({
                provider_id: '',
                priority: this.routes.length + 1,
                is_active: true
            });
        },
        removeRoute(index) {
            this.routes.splice(index, 1);
        }
     }">
    <div class="flex items-center justify-between border-b border-slate-700/60 pb-3">
        <div>
            <h2 class="text-base font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                <span>Pemetaan Multi-Provider & Failover Routing</span>
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">Konfigurasi provider utama dan urutan provider cadangan (fallback) saat provider utama down.</p>
        </div>
        <button type="button"
                @click="addRoute()"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 border border-indigo-500/30 text-xs font-semibold transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>Tambah Provider Cadangan</span>
        </button>
    </div>

    <form method="POST" action="{{ route('admin.products.routes.update', $product) }}" class="space-y-4">
        @csrf

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-700/60 bg-slate-900/40 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                        <th class="py-2.5 px-3">Provider Upstream</th>
                        <th class="py-2.5 px-3 text-center">Prioritas (1 = Utama)</th>
                        <th class="py-2.5 px-3 text-center">Status Route</th>
                        <th class="py-2.5 px-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    <template x-for="(route, idx) in routes" :key="idx">
                        <tr class="hover:bg-slate-700/30">
                            {{-- Provider Select --}}
                            <td class="py-2.5 px-3">
                                <select :name="'routes[' + idx + '][provider_id]'"
                                        x-model="route.provider_id"
                                        required
                                        class="w-full bg-slate-950 border border-slate-700 rounded-lg px-2.5 py-1 text-xs text-white focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                    <option value="">-- Pilih Provider --</option>
                                    @foreach($providers as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->driver ?? $p->code }})</option>
                                    @endforeach
                                </select>
                            </td>

                            {{-- Priority Input --}}
                            <td class="py-2.5 px-3 text-center">
                                <input type="number"
                                       :name="'routes[' + idx + '][priority]'"
                                       x-model="route.priority"
                                       min="1"
                                       max="10"
                                       required
                                       class="w-16 mx-auto text-center bg-slate-950 border border-slate-700 rounded-lg py-1 text-xs text-white font-mono focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            </td>

                            {{-- Active Toggle --}}
                            <td class="py-2.5 px-3 text-center">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox"
                                           :name="'routes[' + idx + '][is_active]'"
                                           value="1"
                                           x-model="route.is_active"
                                           class="sr-only peer">
                                    <div class="w-8 h-4 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-emerald-600 relative"></div>
                                </label>
                            </td>

                            {{-- Action Remove --}}
                            <td class="py-2.5 px-3 text-right">
                                <button type="button"
                                        @click="removeRoute(idx)"
                                        class="p-1 rounded text-slate-500 hover:text-rose-400 hover:bg-rose-500/10 transition-colors"
                                        title="Hapus route ini">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between pt-2 border-t border-slate-700/40">
            <span class="text-[11px] text-slate-400">Pastikan prioritas 1 adalah provider utama, diikuti prioritas 2 dan seterusnya untuk cadangan.</span>
            <button type="submit"
                    class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition-all">
                Simpan Konfigurasi Routing
            </button>
        </div>
    </form>
</div>
