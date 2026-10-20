<x-admin.layouts.app>
    <div class="space-y-6 max-w-6xl mx-auto"
         x-data="{
            showAddModal: false,
            showEditModal: false,
            editOperator: { id: null, name: '', code: '', category_id: '', icon_url: '', prefixes: '', sort_order: 0, is_active: true },
            testNumber: '',
            detectedOperator: null,
            isDetecting: false,
            detectPrefix() {
                if (this.testNumber.trim().length < 4) {
                    this.detectedOperator = null;
                    return;
                }
                this.isDetecting = true;
                fetch('{{ route('admin.operators.detect') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ number: this.testNumber })
                })
                .then(r => r.json())
                .then(d => {
                    this.detectedOperator = d.detected ? d.operator : null;
                })
                .finally(() => this.isDetecting = false);
            },
            openEdit(op) {
                this.editOperator = {
                    id: op.id,
                    name: op.name,
                    code: op.code,
                    category_id: op.category_id || '',
                    icon_url: op.icon_url || '',
                    prefixes: (op.prefix_rules || []).join(', '),
                    sort_order: op.sort_order || 0,
                    is_active: Boolean(op.is_active)
                };
                this.showEditModal = true;
            }
         }">

        {{-- Flash Alerts --}}
        @if(session('success'))
            <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>{{ session('success') }}</span>
                </div>
                <button type="button" @click="$el.parentElement.remove()" class="text-emerald-400/70 hover:text-emerald-400">✕</button>
            </div>
        @endif

        @if($errors->any())
            <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm">
                <div class="font-bold mb-1">Gagal menyimpan data operator:</div>
                <ul class="list-disc list-inside text-xs space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Top Bar & Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-slate-900/60 backdrop-blur-md p-6 rounded-2xl border border-slate-800">
            <div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.products.index') }}" class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    </a>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2.5">
                            <span>Master Operator & Brand</span>
                            <span class="text-xs px-2.5 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-mono font-bold">
                                {{ $operators->total() }} Brand
                            </span>
                        </h1>
                        <p class="text-xs text-slate-400 mt-1">Kelola brand/operator (Telkomsel, PLN, DANA, dll) beserta prefix nomor HP untuk deteksi otomatis di aplikasi.</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('admin.categories.index') }}"
                   class="px-3.5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-semibold border border-slate-700 transition-all">
                    Kategori Produk
                </a>
                <button type="button"
                        @click="showAddModal = true"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg shadow-indigo-600/30 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>+ Tambah Operator</span>
                </button>
            </div>
        </div>

        {{-- Live Prefix Tester Interactive Card --}}
        <div class="bg-gradient-to-r from-slate-900 to-indigo-950/40 rounded-2xl border border-indigo-500/20 p-5 shadow-xl space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-indigo-300">Uji Deteksi Otomatis Prefix Operator</h3>
                </div>
                <span class="text-[11px] text-slate-400">Realtime prefix rule test</span>
            </div>

            <div class="flex flex-col sm:flex-row items-center gap-4">
                <div class="w-full sm:w-80 relative">
                    <input type="text"
                           x-model="testNumber"
                           @input.debounce.300ms="detectPrefix()"
                           placeholder="Ketik nomor HP (contoh: 08123456789)..."
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white font-mono focus:outline-none focus:border-indigo-500 pr-10">
                    <div x-show="isDetecting" class="absolute right-3 top-2.5 text-indigo-400 text-xs">
                        <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </div>
                </div>

                <div class="flex-1 w-full">
                    <template x-if="detectedOperator">
                        <div class="flex items-center gap-3 bg-emerald-500/10 border border-emerald-500/20 px-4 py-2 rounded-xl">
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                            <span class="text-xs text-emerald-300 font-semibold">Terdeteksi:</span>
                            <span class="text-xs font-bold text-white uppercase" x-text="detectedOperator.name"></span>
                            <span class="font-mono text-[10px] px-2 py-0.5 rounded bg-slate-900 text-emerald-400 border border-emerald-500/30 font-bold" x-text="detectedOperator.code"></span>
                            <span class="text-xs text-slate-400 font-medium" x-text="'(' + (detectedOperator.category || 'Umum') + ')'"></span>
                        </div>
                    </template>
                    <template x-if="!detectedOperator && testNumber.trim().length >= 4 && !isDetecting">
                        <div class="flex items-center gap-2 bg-amber-500/10 border border-amber-500/20 px-4 py-2 rounded-xl text-xs text-amber-300">
                            <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <span>Nomor ini belum terdaftar di aturan prefix operator manapun.</span>
                        </div>
                    </template>
                    <template x-if="testNumber.trim().length < 4">
                        <p class="text-xs text-slate-400">Masukkan minimal 4 digit awalan nomor telepon untuk melihat hasil deteksi operator.</p>
                    </template>
                </div>
            </div>
        </div>

        {{-- Filter & Search Form --}}
        <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 p-4">
            <form method="GET" action="{{ route('admin.operators.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama atau kode operator..."
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <select name="category_id" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="">-- Semua Kategori --</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold border border-slate-700 transition-all flex-1">
                        Filter
                    </button>
                    @if(request()->anyFilled(['search', 'category_id']))
                        <a href="{{ route('admin.operators.index') }}" class="px-3 py-2 rounded-xl bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 text-xs font-semibold border border-rose-500/20">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Operators Table --}}
        <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/60 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                            <th class="py-3.5 px-4">Operator / Brand</th>
                            <th class="py-3.5 px-4">Kategori</th>
                            <th class="py-3.5 px-4">Daftar Prefix Nomor HP</th>
                            <th class="py-3.5 px-4 text-center">Produk Terkait</th>
                            <th class="py-3.5 px-4 text-center">Urutan</th>
                            <th class="py-3.5 px-4 text-center">Status</th>
                            <th class="py-3.5 px-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @forelse($operators as $op)
                            <tr class="hover:bg-slate-800/40 transition-colors">
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-3">
                                        @if($op->icon_url)
                                            <img src="{{ $op->icon_url }}" alt="{{ $op->name }}" class="w-8 h-8 rounded-lg object-contain bg-slate-800 p-1">
                                        @else
                                            <div class="w-8 h-8 rounded-lg bg-indigo-600/20 text-indigo-400 flex items-center justify-center font-bold text-xs uppercase border border-indigo-500/20">
                                                {{ substr($op->name, 0, 2) }}
                                            </div>
                                        @endif
                                        <div>
                                            <div class="font-bold text-white text-sm">{{ $op->name }}</div>
                                            <div class="font-mono text-[11px] text-indigo-300 font-semibold">{{ $op->code }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded bg-slate-800 text-slate-300 text-[11px] font-medium">
                                        {{ $op->category?->name ?? 'Semua Kategori' }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 max-w-xs">
                                    @if(!empty($op->prefix_rules))
                                        <div class="flex flex-wrap gap-1">
                                            @foreach((array)$op->prefix_rules as $pfx)
                                                <span class="font-mono text-[10px] bg-slate-950 px-1.5 py-0.5 rounded border border-slate-800 text-slate-300">
                                                    {{ $pfx }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-slate-500 text-[11px]">-</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-center font-mono font-bold text-slate-200">
                                    <a href="{{ route('admin.products.index') }}?operator_id={{ $op->id }}" class="hover:text-indigo-400 transition-colors underline">
                                        {{ $op->products_count }} Produk
                                    </a>
                                </td>
                                <td class="py-3.5 px-4 text-center font-mono text-slate-400">
                                    #{{ $op->sort_order }}
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $op->is_active ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' }}">
                                        {{ $op->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        <button type="button"
                                                @click="openEdit({{ Js::from($op) }})"
                                                class="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-indigo-300 hover:text-white border border-slate-700 text-xs font-semibold transition-all">
                                            Edit
                                        </button>

                                        <form action="{{ route('admin.operators.destroy', $op) }}" method="POST"
                                              onsubmit="return confirm('Hapus operator {{ addslashes($op->name) }}? Produk yang terhubung akan tetap ada.')" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 rounded-lg bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 border border-rose-500/20 transition-all">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-500">
                                    Belum ada operator/brand yang terdaftar. Klik "+ Tambah Operator" untuk membuat operator pertama.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($operators->hasPages())
                <div class="p-4 border-t border-slate-800">
                    {{ $operators->links() }}
                </div>
            @endif
        </div>

        {{-- Modal Tambah Operator --}}
        <div x-show="showAddModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="showAddModal = false"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-lg rounded-2xl bg-slate-900 border border-slate-800 p-6 shadow-2xl space-y-4">
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Tambah Operator Baru</span>
                    </h3>
                    <p class="text-xs text-slate-400">Tambahkan entitas brand/operator baru untuk mengelompokkan produk secara rapi.</p>

                    <form action="{{ route('admin.operators.store') }}" method="POST" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Operator <span class="text-rose-400">*</span></label>
                                <input type="text" name="name" placeholder="Contoh: Telkomsel" required
                                       class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">Kode Unik <span class="text-rose-400">*</span></label>
                                <input type="text" name="code" placeholder="TELKOMSEL" required
                                       class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono uppercase focus:outline-none focus:border-indigo-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">Kategori Produk</label>
                                <select name="category_id" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                                    <option value="">-- Pilih Kategori --</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">Urutan Tampil (Sort Order)</label>
                                <input type="number" name="sort_order" value="0" min="0"
                                       class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">URL Ikon / Logo Operator</label>
                            <input type="text" name="icon_url" placeholder="https://domain.com/icons/telkomsel.png"
                                   class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Aturan Prefix Nomor HP (Pisahkan dengan koma)</label>
                            <textarea name="prefixes" rows="3" placeholder="Contoh: 0811, 0812, 0813, 0821, 0822, 0852"
                                      class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono focus:outline-none focus:border-indigo-500"></textarea>
                            <p class="text-[10px] text-slate-500 mt-1">Digunakan untuk deteksi otomatis operator saat pengguna mengetik nomor tujuan di mobile app.</p>
                        </div>

                        <div>
                            <label class="flex items-center gap-2.5 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" checked
                                       class="rounded bg-slate-950 border-slate-700 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-xs text-slate-300">Operator Aktif</span>
                            </label>
                        </div>

                        <div class="flex justify-end gap-3 pt-3 border-t border-slate-800">
                            <button type="button" @click="showAddModal = false" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs">Batal</button>
                            <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold shadow-lg shadow-indigo-600/30">Simpan Operator</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Modal Edit Operator --}}
        <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="showEditModal = false"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-lg rounded-2xl bg-slate-900 border border-slate-800 p-6 shadow-2xl space-y-4">
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        <span>Edit Operator</span>
                    </h3>

                    <form :action="'{{ url('office/operators') }}/' + editOperator.id" method="POST" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Operator <span class="text-rose-400">*</span></label>
                                <input type="text" name="name" x-model="editOperator.name" required
                                       class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">Kode Unik <span class="text-rose-400">*</span></label>
                                <input type="text" name="code" x-model="editOperator.code" required
                                       class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono uppercase focus:outline-none focus:border-indigo-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">Kategori Produk</label>
                                <select name="category_id" x-model="editOperator.category_id" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                                    <option value="">-- Pilih Kategori --</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">Urutan Tampil (Sort Order)</label>
                                <input type="number" name="sort_order" x-model="editOperator.sort_order" min="0"
                                       class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">URL Ikon / Logo Operator</label>
                            <input type="text" name="icon_url" x-model="editOperator.icon_url"
                                   class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Aturan Prefix Nomor HP (Pisahkan dengan koma)</label>
                            <textarea name="prefixes" x-model="editOperator.prefixes" rows="3"
                                      class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono focus:outline-none focus:border-indigo-500"></textarea>
                        </div>

                        <div>
                            <label class="flex items-center gap-2.5 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" x-model="editOperator.is_active"
                                       class="rounded bg-slate-950 border-slate-700 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-xs text-slate-300">Operator Aktif</span>
                            </label>
                        </div>

                        <div class="flex justify-end gap-3 pt-3 border-t border-slate-800">
                            <button type="button" @click="showEditModal = false" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs">Batal</button>
                            <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold shadow-lg shadow-indigo-600/30">Perbarui Operator</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-admin.layouts.app>
