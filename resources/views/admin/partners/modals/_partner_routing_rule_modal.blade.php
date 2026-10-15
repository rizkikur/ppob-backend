<div x-data="{ open: false }"
     @open-routing-modal.window="open = true"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     role="dialog"
     aria-modal="true">

    {{-- Backdrop --}}
    <div x-show="open"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="open = false"
         class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm transition-opacity"></div>

    {{-- Dialog Modal --}}
    <div class="flex min-h-full items-center justify-center p-4">
        <div x-show="open"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="relative w-full max-w-lg transform overflow-hidden rounded-2xl bg-slate-900 border border-slate-800 p-6 shadow-2xl transition-all">

            <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    <span>Aturan Routing & Failover Mitra</span>
                </h3>
                <button type="button" @click="open = false" class="text-slate-400 hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form action="{{ route('admin.partners.routing_rules.store', $partner) }}" method="POST" class="mt-4 space-y-4">
                @csrf

                {{-- Kategori --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Kategori Produk
                    </label>
                    <select name="category_code" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="">Semua Kategori (Global)</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->code }}">{{ $cat->name }} ({{ strtoupper($cat->code) }})</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-slate-500 mt-1">Pilih kategori spesifik atau biarkan global untuk mencakup seluruh kategori.</p>
                </div>

                {{-- Preferred Provider --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Preferred Supplier (Supplier Utama)
                    </label>
                    <select name="preferred_provider_id" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="">Otomatis (Sesuai Prioritas Tertinggi Produk)</option>
                        @foreach($providers as $prov)
                            <option value="{{ $prov->id }}">{{ $prov->name }} ({{ strtoupper($prov->code) }})</option>
                        @endforeach
                    </select>
                </div>

                {{-- Allow Failover Toggle --}}
                <div class="pt-2">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="allow_failover" value="1" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                        <span class="ml-3 text-xs font-semibold text-slate-200">Izinkan Pengalihan ke Supplier Cadangan (Failover)</span>
                    </label>
                    <p class="text-[11px] text-slate-500 mt-1">Jika dinonaktifkan, request transaksi mitra akan langsung ditolak (HTTP 503) saat supplier utama down.</p>
                </div>

                {{-- Failover Policy --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Kebijakan Cakupan Failover
                    </label>
                    <select name="failover_policy" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="none">none (Jangan failover sama sekali)</option>
                        <option value="same_category" selected>same_category (Hanya cadangan kategori sejenis)</option>
                        <option value="any">any (Bebas pilih supplier aktif apa saja)</option>
                    </select>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="button" @click="open = false" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-xs rounded-xl transition-colors">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl transition-all shadow-lg shadow-indigo-600/30">
                        Simpan Aturan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
