<x-admin.layouts.app>
    <div class="space-y-6 max-w-5xl mx-auto" x-data="{ showAddModal: false, editCat: null }">
        {{-- Flash Alerts --}}
        @if(session('success'))
            <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-center justify-between">
                <span>{{ session('success') }}</span>
                <button type="button" @click="$el.parentElement.remove()" class="text-emerald-400/70 hover:text-emerald-400">✕</button>
            </div>
        @endif
        @if(session('error'))
            <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm flex items-center justify-between">
                <span>{{ session('error') }}</span>
                <button type="button" @click="$el.parentElement.remove()" class="text-rose-400/70 hover:text-rose-400">✕</button>
            </div>
        @endif

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.products.index') }}"
                   class="p-2 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700/60 text-slate-400 hover:text-white transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-white">Kategori Produk</h1>
                    <p class="text-xs text-slate-400 mt-1">Kelola grup kategori (Pulsa, Paket Data, PLN, E-Wallet, Voucher Game).</p>
                </div>
            </div>
            <button type="button"
                    @click="showAddModal = true"
                    class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition-all flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Tambah Kategori</span>
            </button>
        </div>

        <div class="bg-slate-800/80 backdrop-blur-md rounded-2xl border border-slate-700/60 shadow-xl overflow-hidden">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-700/60 bg-slate-900/40 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                        <th class="py-3.5 px-4">Urutan</th>
                        <th class="py-3.5 px-4">Kode</th>
                        <th class="py-3.5 px-4">Nama Kategori</th>
                        <th class="py-3.5 px-4 text-center">Jumlah Produk</th>
                        <th class="py-3.5 px-4 text-center">Status</th>
                        <th class="py-3.5 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    @forelse($categories as $cat)
                        <tr class="hover:bg-slate-700/30">
                            <td class="py-3 px-4 font-mono text-slate-400">{{ $cat->sort_order }}</td>
                            <td class="py-3 px-4 font-mono font-bold text-indigo-400">{{ $cat->code }}</td>
                            <td class="py-3 px-4 font-medium text-slate-200">{{ $cat->name }}</td>
                            <td class="py-3 px-4 text-center font-mono text-slate-300">{{ $cat->products_count }} Produk</td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold {{ $cat->is_active ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' }}">
                                    {{ $cat->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button"
                                            @click="editCat = { id: {{ $cat->id }}, name: '{{ addslashes($cat->name) }}', code: '{{ $cat->code }}', sort_order: {{ $cat->sort_order }}, is_active: {{ $cat->is_active ? 'true' : 'false' }} }"
                                            class="px-2.5 py-1 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-200 text-xs">
                                        Edit
                                    </button>
                                    @if($cat->products_count === 0)
                                        <form method="POST" action="{{ route('admin.categories.destroy', $cat) }}" onsubmit="return confirm('Hapus kategori ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="px-2.5 py-1 rounded-lg bg-rose-500/20 text-rose-400 hover:bg-rose-500/30 text-xs">Hapus</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-500">Belum ada kategori yang terdaftar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Modal Tambah Kategori --}}
        <div x-show="showAddModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="showAddModal = false"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-md rounded-2xl bg-slate-900 border border-slate-800 p-6 shadow-2xl">
                    <h3 class="text-base font-bold text-white mb-4">Tambah Kategori Baru</h3>
                    <form method="POST" action="{{ route('admin.categories.store') }}" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Kode Kategori (Huruf Besar)</label>
                            <input type="text" name="code" required placeholder="PULSA, DATA, PLN" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs font-mono uppercase text-white">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Kategori</label>
                            <input type="text" name="name" required placeholder="Pulsa Reguler" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Urutan Tampilan</label>
                            <input type="number" name="sort_order" value="0" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono">
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="showAddModal = false" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs">Batal</button>
                            <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-semibold">Simpan Kategori</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Modal Edit Kategori --}}
        <div x-show="editCat !== null" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="editCat = null"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-md rounded-2xl bg-slate-900 border border-slate-800 p-6 shadow-2xl">
                    <h3 class="text-base font-bold text-white mb-4">Edit Kategori</h3>
                    <form :action="'{{ url('admin/categories') }}/' + editCat?.id" method="POST" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Kode Kategori</label>
                            <input type="text" name="code" x-model="editCat.code" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs font-mono uppercase text-white">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Kategori</label>
                            <input type="text" name="name" x-model="editCat.name" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Urutan Tampilan</label>
                            <input type="number" name="sort_order" x-model="editCat.sort_order" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono">
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="editCat = null" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs">Batal</button>
                            <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-semibold">Perbarui Kategori</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-admin.layouts.app>
