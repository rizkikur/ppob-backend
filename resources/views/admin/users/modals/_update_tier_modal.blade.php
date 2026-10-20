<div x-data="{ show: false }"
     @open-tier-modal.window="show = true"
     x-show="show"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     role="dialog"
     aria-modal="true">
    <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="show = false"></div>

    <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative w-full max-w-sm rounded-2xl bg-slate-900 border border-slate-800 p-6 shadow-2xl space-y-4">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                <span>Ubah Level Tier Pengguna</span>
            </h3>

            <form action="{{ route('admin.users.tier.update', $user) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="modal_user_tier_id" class="block text-xs font-semibold text-slate-300 mb-1">Pilih Tier Baru <span class="text-rose-400">*</span></label>
                    <select id="modal_user_tier_id" name="user_tier_id" required
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                        @foreach($tiers as $tier)
                            @if($tier->name !== 'partner')
                                <option value="{{ $tier->id }}" @selected($user->user_tier_id === $tier->id)>
                                    {{ $tier->label }} - {{ $tier->description }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                    <p class="text-[10px] text-slate-500 mt-1">Perubahan tier akan otomatis menyesuaikan harga jual produk saat pengguna bertransaksi.</p>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-800">
                    <button type="button" @click="show = false" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold">Batal</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg shadow-indigo-600/30">Simpan Tier</button>
                </div>
            </form>
        </div>
    </div>
</div>
