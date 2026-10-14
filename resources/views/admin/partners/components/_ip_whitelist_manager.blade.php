{{-- IP Whitelist Manager Component --}}
<div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-6">
    <div class="flex items-center justify-between mb-4 pb-4 border-b border-slate-800">
        <div>
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                <span>IP Whitelist Server Mitra</span>
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">Hanya alamat IP atau subnet CIDR di bawah yang diizinkan menembus API Gateway</p>
        </div>

        <span class="text-xs font-semibold px-2.5 py-1 bg-slate-800 rounded-lg text-slate-300">
            {{ count($partner->allowed_ips ?? []) }} IP Terdaftar
        </span>
    </div>

    {{-- Add IP Form --}}
    <form action="{{ route('admin.partners.ip.store', $partner) }}" method="POST" class="mb-5">
        @csrf
        <div class="flex flex-col sm:flex-row gap-2">
            <div class="relative flex-1">
                <input type="text" name="ip" placeholder="Masukkan IP atau CIDR (contoh: 103.19.20.1 atau 103.19.20.0/24 atau *)" required
                       class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-xs font-mono text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
            </div>
            <button type="submit"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-xl transition-all shadow-md shadow-indigo-600/20 whitespace-nowrap flex items-center justify-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                <span>Tambah IP</span>
            </button>
        </div>
        <p class="text-[11px] text-slate-500 mt-1">Gunakan tanda bintang <code>*</code> untuk mengizinkan semua IP (tidak direkomendasikan untuk production).</p>
    </form>

    {{-- Whitelisted IP Badges / List --}}
    @if(empty($partner->allowed_ips))
        <div class="p-4 bg-amber-500/10 border border-amber-500/20 rounded-xl text-xs text-amber-300 flex items-center gap-2">
            <svg class="w-4 h-4 text-amber-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>Belum ada IP terdaftar. API partner saat ini terbuka untuk semua alamat IP. Segera daftarkan IP server mitra.</span>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            @foreach($partner->allowed_ips as $ip)
                <div class="flex items-center justify-between bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 font-mono text-xs text-slate-200">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        <span>{{ $ip }}</span>
                    </div>
                    <form action="{{ route('admin.partners.ip.destroy', ['partner' => $partner, 'ip' => urlencode($ip)]) }}" method="POST"
                          onsubmit="return confirm('Apakah Anda yakin ingin menghapus IP {{ $ip }} dari whitelist?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-slate-500 hover:text-rose-400 p-1 transition-colors" title="Hapus IP">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
</div>
