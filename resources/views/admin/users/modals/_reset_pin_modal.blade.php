<div x-data="{ show: false }"
     @open-pin-modal.window="show = true"
     x-show="show"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     role="dialog"
     aria-modal="true">
    <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="show = false"></div>

    <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative w-full max-w-sm rounded-2xl bg-slate-900 border border-slate-800 p-6 shadow-2xl space-y-4">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <span>Reset PIN Transaksi</span>
            </h3>

            <p class="text-xs text-slate-300">
                PIN transaksi akun <strong class="text-white">{{ $user->name }}</strong> akan dikosongkan. Pengguna akan diwajibkan membuat PIN baru saat membuka aplikasi atau bertransaksi berikutnya.
            </p>

            <form action="{{ route('admin.users.reset_pin', $user) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="pin_reason" class="block text-xs font-semibold text-slate-300 mb-1">Alasan Reset PIN</label>
                    <input type="text" id="pin_reason" name="reason" placeholder="Permintaan pengguna via CS (lupa PIN)"
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-800">
                    <button type="button" @click="show = false" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold">Batal</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold shadow-lg shadow-amber-600/30">Ya, Reset PIN</button>
                </div>
            </form>
        </div>
    </div>
</div>
