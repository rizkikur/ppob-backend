{{-- Confirm Key / Secret Rotation Modal --}}
<div x-data="{
        open: false,
        type: 'secret', // 'key' or 'secret'
        title: '',
        desc: '',
        url: '',
        actionLabel: ''
     }"
     @open-rotate-modal.window="
        open = true;
        type = $event.detail.type;
        title = type === 'secret' ? 'Konfirmasi Rotasi API Secret' : 'Konfirmasi Rotasi Public API Key';
        desc = type === 'secret'
            ? 'PERINGATAN: Memutar (rotate) API Secret akan langsung membatalkan kunci rahasia lama. Semua request H2H dari mitra yang masih memakai secret lama akan ditolak 401 Unauthorized sampai mereka memperbarui kunci di sistem mereka.'
            : 'PERINGATAN: Memutar Public API Key akan langsung membatalkan identifier lama. Mitra harus segera memperbarui header X-Api-Key mereka.';
        url = type === 'secret'
            ? '{{ route('admin.partners.rotate_secret', $partner ?? 0) }}'
            : '{{ route('admin.partners.rotate_key', $partner ?? 0) }}';
        actionLabel = type === 'secret' ? 'Ya, Rotate API Secret Sekarang' : 'Ya, Rotate API Key';
     "
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm transition-opacity" @click="open = false"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

        <div class="relative inline-block align-bottom bg-slate-900 border border-rose-500/30 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full p-6 text-white">
            <div class="flex items-center gap-3 mb-4">
                <div class="p-3 bg-rose-500/10 border border-rose-500/30 rounded-xl text-rose-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-white" x-text="title"></h3>
                    <p class="text-xs text-slate-400">Tindakan keamanan berisiko tinggi terhadap operasional mitra</p>
                </div>
            </div>

            <p class="text-xs text-rose-300 bg-rose-500/10 border border-rose-500/20 rounded-xl p-3 mb-4 leading-relaxed" x-text="desc"></p>

            <form :action="url" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Alasan Rotasi Kunci (Opsional)</label>
                    <input type="text" name="reason" placeholder="Contoh: Permintaan tim teknis mitra / dugaan kebocoran kredensial"
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-rose-500 focus:ring-1 focus:ring-rose-500">
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
                    <button type="button" @click="open = false"
                            class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium text-xs rounded-xl transition-colors">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs rounded-xl transition-all shadow-md shadow-rose-600/30"
                            x-text="actionLabel">
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
