{{-- MODAL AUTO-SYNC PRICELIST PROVIDER --}}
<div x-show="showSyncModal" x-cloak
     class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div @click.away="showSyncModal = false"
         class="bg-slate-900 border border-slate-800 rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-5">
        
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Auto-Sync Pricelist Provider</h3>
                    <p class="text-xs text-slate-400">Tarik otomatis HPP modal & impor SKU baru langsung dari provider.</p>
                </div>
            </div>
            <button type="button" @click="showSyncModal = false" class="text-slate-400 hover:text-white p-1">✕</button>
        </div>

        <form method="POST" action="{{ route('admin.products.sync') }}" class="space-y-4">
            @csrf

            <div>
                <label for="sync_provider_id" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                    Pilih Provider Upstream <span class="text-rose-400">*</span>
                </label>
                <select id="sync_provider_id"
                        name="provider_id"
                        required
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500/50">
                    <option value="">-- Pilih Provider --</option>
                    @foreach($providers as $prov)
                        <option value="{{ $prov->id }}">{{ $prov->name }} (Driver: {{ $prov->driver ?? $prov->code }})</option>
                    @endforeach
                </select>
            </div>

            <div class="p-4 rounded-xl bg-slate-950/70 border border-slate-800 text-xs text-slate-300 space-y-2">
                <div class="font-semibold text-blue-400 flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Perilaku Sinkronisasi:</span>
                </div>
                <ul class="list-disc list-inside space-y-1 text-slate-400 pl-1">
                    <li>HPP modal (<code class="text-slate-200">base_price_cents</code>) produk yang sudah ada akan diperbarui mengikuti tarif terbaru.</li>
                    <li>Sistem memberikan peringatan jika ada harga jual tier yang berpotensi rugi (di bawah modal baru).</li>
                    <li>SKU baru yang belum ada di database akan otomatis didaftarkan ke katalog produk.</li>
                </ul>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-800">
                <button type="button" @click="showSyncModal = false" class="px-4 py-2 rounded-xl text-xs font-medium text-slate-400 hover:text-white bg-slate-800">
                    Batal
                </button>
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-lg shadow-blue-600/30 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>Mulai Sinkronisasi</span>
                </button>
            </div>
        </form>
    </div>
</div>
