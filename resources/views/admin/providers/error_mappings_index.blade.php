@extends('admin.layouts.app')

@section('title', 'Kamus Response Code & Error Mappings')

@section('content')
<div class="space-y-6" x-data="{
    showAddSystemCodeModal: false,
    showEditSystemCodeModal: false,
    editingSystemCode: {
        id: null,
        rc: '',
        slug: '',
        name: '',
        status_type: 'FAILED',
        default_message: '',
        is_retryable: false,
        is_system: false,
        update_url: ''
    },
    openEditSystemCode(item) {
        this.editingSystemCode = {
            id: item.id,
            rc: item.rc,
            slug: item.slug,
            name: item.name,
            status_type: item.status_type,
            default_message: item.default_message,
            is_retryable: Boolean(item.is_retryable),
            is_system: Boolean(item.is_system),
            update_url: '{{ url('/office/error-mappings/system-codes') }}/' + item.id
        };
        this.showEditSystemCodeModal = true;
    },
    showAddModal: false,
    showEditModal: false,
    editing: {
        id: null,
        provider_id: '',
        provider_name: '',
        upstream_code: '',
        internal_code: 'ERR_GENERAL',
        custom_message: '',
        is_retryable: false,
        update_url: ''
    },
    openEdit(item) {
        this.editing = {
            id: item.id,
            provider_id: item.provider_id,
            provider_name: item.provider ? item.provider.name : '',
            upstream_code: item.upstream_code,
            internal_code: item.internal_code,
            custom_message: item.custom_message || '',
            is_retryable: Boolean(item.is_retryable),
            update_url: '{{ url('/office/error-mappings') }}/' + item.id
        };
        this.showEditModal = true;
    }
}">

    {{-- Top Header --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-indigo-400 mb-1 uppercase tracking-wider">
                <a href="{{ route('admin.providers.index') }}" class="hover:underline">Vendor / Supplier</a>
                <span>/</span>
                <span>Kamus Response Code</span>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2.5">
                <span>Kamus Response Code (RC) & Pemetaan Error</span>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-mono">
                    Redis In-Memory Cached (~0.1ms)
                </span>
            </h1>
            <p class="text-xs text-slate-400 mt-1 max-w-3xl">
                Standarisasi Response Code (RC) numerik dan kode error internal sistem PPOB. Semua data disimpan di Redis in-memory cache untuk performa transaksi instan tanpa membebani database utama.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <button type="button"
                    @click="showAddSystemCodeModal = true"
                    class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold border border-slate-700 shadow-sm transition-all">
                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>+ Tambah Standar Kode Internal</span>
            </button>

            <button type="button"
                    @click="showAddModal = true"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg shadow-indigo-600/30 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>+ Petakan RC Supplier</span>
            </button>
        </div>
    </div>

    {{-- 1. Bagian Standar Kode Response Code (RC) Internal Sistem --}}
    <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 p-6 shadow-xl space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-800">
            <div>
                <h2 class="text-base font-bold text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    <span>Standar Kode Error Internal Sistem PPOB</span>
                    <span class="text-xs font-normal text-slate-400">({{ $systemCodes->count() }} Kode Terdaftar)</span>
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">
                    Daftar bahasa baku sistem internal yang dikirimkan ke aplikasi klien atau mitra B2B/H2H. Dilengkapi kode numerik 2-digit standar PPOB nasional / ISO 8583.
                </p>
            </div>

            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-950 border border-slate-800 text-[11px] text-slate-400 font-medium self-start sm:self-auto">
                <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                <span>Tabel dapat digeser ke samping</span>
            </div>
        </div>

        <div class="rounded-xl border border-slate-800 bg-slate-950/40 overflow-hidden">
            <div class="overflow-x-auto scrollbar-thin scrollbar-thumb-slate-700 scrollbar-track-slate-950/60">
                <table class="w-full text-left border-collapse text-xs min-w-[1050px]">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/90 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                            <th class="py-3.5 px-4 w-[100px] min-w-[100px] whitespace-nowrap text-center">Kode RC</th>
                            <th class="py-3.5 px-4 w-[200px] min-w-[200px] whitespace-nowrap">Identifier / Slug</th>
                            <th class="py-3.5 px-4 min-w-[220px]">Nama / Label Standar</th>
                            <th class="py-3.5 px-4 w-[110px] min-w-[110px] whitespace-nowrap text-center">Status</th>
                            <th class="py-3.5 px-4 min-w-[280px]">Pesan Default Pengguna</th>
                            <th class="py-3.5 px-4 w-[140px] min-w-[140px] whitespace-nowrap text-center">Auto-Failover?</th>
                            <th class="py-3.5 px-4 w-[110px] min-w-[110px] whitespace-nowrap text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @foreach($systemCodes as $sc)
                            @php
                                $statusBadge = match($sc->status_type) {
                                    'SUCCESS' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                    'PENDING' => 'bg-amber-500/10 text-amber-300 border-amber-500/20',
                                    default => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                                };
                            @endphp
                            <tr class="hover:bg-slate-800/40 transition-colors">
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-lg font-mono font-bold text-sm bg-slate-950 text-indigo-400 border border-slate-800 shadow-sm">
                                        {{ $sc->rc }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 font-mono font-bold text-white whitespace-nowrap">
                                    <span class="text-indigo-300">{{ $sc->slug }}</span>
                                    @if($sc->is_system)
                                        <span class="ml-1 px-1.5 py-0.2 rounded text-[9px] font-semibold bg-slate-800 text-slate-400 uppercase">Core</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 font-medium text-slate-200">
                                    {{ $sc->name }}
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border {{ $statusBadge }}">
                                        {{ $sc->status_type }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-slate-300">
                                    {{ $sc->default_message }}
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold {{ $sc->is_retryable ? 'bg-indigo-500/10 text-indigo-300 border border-indigo-500/20' : 'bg-slate-800/80 text-slate-500' }}">
                                        {{ $sc->is_retryable ? 'Ya (Retryable)' : 'Tidak' }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right space-x-1 whitespace-nowrap">
                                    <button type="button"
                                            @click="openEditSystemCode({{ json_encode($sc) }})"
                                            class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-indigo-300 hover:text-white border border-slate-700 transition-all"
                                            title="Edit Standar Kode RC">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>

                                    @if(!$sc->is_system)
                                        <form action="{{ route('admin.error_mappings.system_codes.destroy', $sc) }}" method="POST"
                                              onsubmit="return confirm('Hapus standar kode RC [{{ $sc->rc }} - {{ $sc->slug }}]?')" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 rounded-lg bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 border border-rose-500/20 transition-all" title="Hapus Kode">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- 2. Toolbar Filter & Pencarian Pemetaan Supplier --}}
    <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 p-4">
        <form method="GET" action="{{ route('admin.error_mappings.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
            {{-- Search --}}
            <div class="md:col-span-2">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari Response Code (RC), pesan, atau vendor..."
                       class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>

            {{-- Filter Provider --}}
            <div>
                <select name="provider_id" onchange="this.form.submit()"
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-300 focus:outline-none focus:border-indigo-500">
                    <option value="">Semua Provider</option>
                    @foreach($providers as $p)
                        <option value="{{ $p->id }}" {{ request('provider_id') == $p->id ? 'selected' : '' }}>{{ $p->name }} ({{ $p->code }})</option>
                    @endforeach
                </select>
            </div>

            {{-- Filter Kode Internal --}}
            <div>
                <select name="internal_code" onchange="this.form.submit()"
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-300 focus:outline-none focus:border-indigo-500">
                    <option value="">Semua Kode Internal</option>
                    @foreach($systemCodes as $sc)
                        <option value="{{ $sc->slug }}" {{ request('internal_code') === $sc->slug ? 'selected' : '' }}>
                            RC {{ $sc->rc }} &bull; {{ $sc->slug }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Tombol Aksi --}}
            <div class="flex items-center gap-2">
                <button type="submit" class="w-full py-2 px-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold border border-slate-700">
                    Cari / Filter
                </button>
                @if(request()->hasAny(['search', 'provider_id', 'internal_code', 'is_retryable']))
                    <a href="{{ route('admin.error_mappings.index') }}" class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white border border-slate-700" title="Reset Filter">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- 3. Tabel Seluruh Pemetaan Response Code Supplier --}}
    <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 p-6 shadow-xl space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-800">
            <div>
                <h3 class="text-base font-bold text-white uppercase tracking-wider flex items-center gap-2">
                    <span>Daftar Pemetaan Response Code Supplier</span>
                    <span class="text-xs font-normal text-slate-400">({{ $mappings->total() }} Aturan Mapping)</span>
                </h3>
                <p class="text-xs text-slate-400 mt-0.5">Pemetaan otomatis setiap kali upstream supplier mengembalikan kode respon.</p>
            </div>

            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-950 border border-slate-800 text-[11px] text-slate-400 font-medium self-start sm:self-auto">
                <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                <span>Tabel dapat digeser ke samping</span>
            </div>
        </div>

        <div class="rounded-xl border border-slate-800 bg-slate-950/40 overflow-hidden">
            <div class="overflow-x-auto scrollbar-thin scrollbar-thumb-slate-700 scrollbar-track-slate-950/60">
                <table class="w-full text-left border-collapse text-xs min-w-[1050px]">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/90 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                            <th class="py-3.5 px-4 w-[220px] min-w-[220px] whitespace-nowrap">Provider / Supplier</th>
                            <th class="py-3.5 px-4 w-[140px] min-w-[140px] whitespace-nowrap">RC Upstream</th>
                            <th class="py-3.5 px-4 w-[220px] min-w-[220px] whitespace-nowrap">Kode Internal Sistem</th>
                            <th class="py-3.5 px-4 min-w-[280px]">Pesan Ramah Pengguna (Custom Message)</th>
                            <th class="py-3.5 px-4 w-[130px] min-w-[130px] whitespace-nowrap text-center">Bisa Retry?</th>
                            <th class="py-3.5 px-4 w-[120px] min-w-[120px] whitespace-nowrap text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @forelse($mappings as $m)
                            @php
                                $def = $standardErrors[$m->internal_code] ?? null;
                                $rcTag = $def['rc'] ?? '05';
                                $isSuccess = $m->internal_code === 'SUCCESS';
                            @endphp
                            <tr class="hover:bg-slate-800/40 transition-colors">
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <a href="{{ route('admin.providers.show', $m->provider_id) }}?tab=errors" class="font-semibold text-white hover:text-indigo-400">
                                        {{ $m->provider?->name ?? 'Provider #' . $m->provider_id }}
                                    </a>
                                    <span class="block text-[10px] font-mono text-slate-500">{{ $m->provider?->code }}</span>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span class="px-2.5 py-1 rounded font-mono font-bold text-sm bg-slate-950 text-amber-400 border border-slate-800">
                                        {{ $m->upstream_code }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <div class="flex items-center gap-1.5">
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-800 text-slate-300 border border-slate-700">
                                            RC {{ $rcTag }}
                                        </span>
                                        <span class="px-2 py-0.5 rounded text-[11px] font-mono font-bold {{ $isSuccess ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' }}">
                                            {{ $m->internal_code }}
                                        </span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 text-slate-200">
                                    {{ $m->custom_message ?: ($def['default_message'] ?? '-') }}
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold {{ $m->is_retryable ? 'bg-indigo-500/10 text-indigo-300 border border-indigo-500/20' : 'bg-slate-800 text-slate-400' }}">
                                        {{ $m->is_retryable ? 'Ya (Retryable)' : 'Tidak' }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right space-x-1 whitespace-nowrap">
                                    {{-- Tombol Edit --}}
                                    <button type="button"
                                            @click="openEdit({{ json_encode($m) }})"
                                            class="p-1.5 rounded-lg bg-indigo-500/10 text-indigo-400 hover:bg-indigo-500/20 border border-indigo-500/20 transition-all"
                                            title="Edit Mapping">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>

                                    {{-- Tombol Hapus --}}
                                    <form action="{{ route('admin.error_mappings.destroy', $m) }}" method="POST"
                                          onsubmit="return confirm('Hapus pemetaan error code [{{ $m->upstream_code }}] untuk {{ $m->provider?->name }}?')" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1.5 rounded-lg bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 border border-rose-500/20 transition-all" title="Hapus Mapping">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-12 text-center text-slate-500">
                                    Tidak ada data pemetaan Response Code yang sesuai dengan kriteria filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Pagination --}}
        @if($mappings->hasPages())
            <div class="pt-4 border-t border-slate-800">
                {{ $mappings->links() }}
            </div>
        @endif
    </div>

    {{-- Modal Tambah Standar Kode Internal --}}
    <div x-show="showAddSystemCodeModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="showAddSystemCodeModal = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-lg rounded-2xl bg-slate-900 border border-slate-800 p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Tambah Standar Kode Error Internal Sistem</span>
                    </h3>
                    <button type="button" @click="showAddSystemCodeModal = false" class="text-slate-400 hover:text-white">✕</button>
                </div>
                <p class="text-xs text-slate-400">Kode ini menjadi bahasa baku sistem PPOB Anda yang akan diterima oleh aplikasi mobile, web kasir, atau mitra H2H.</p>

                <form action="{{ route('admin.error_mappings.system_codes.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Kode RC (2-Digit / ISO) <span class="text-rose-400">*</span></label>
                            <input type="text" name="rc" placeholder="Contoh: 88, 14, 55" required maxlength="10"
                                   class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Tipe Status Transaksi <span class="text-rose-400">*</span></label>
                            <select name="status_type" required
                                    class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                                <option value="FAILED">FAILED (Transaksi Gagal)</option>
                                <option value="PENDING">PENDING (Antrean / Menunggu)</option>
                                <option value="SUCCESS">SUCCESS (Transaksi Sukses)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Identifier Slug Internal (Huruf Kapital & Garis Bawah) <span class="text-rose-400">*</span></label>
                        <input type="text" name="slug" placeholder="Contoh: ERR_BILL_ALREADY_PAID, ERR_BLOCKED" required
                               class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white font-mono uppercase focus:outline-none focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Nama / Label Standar Error <span class="text-rose-400">*</span></label>
                        <input type="text" name="name" placeholder="Contoh: Tagihan Sudah Terbayar (Lunas)" required
                               class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Pesan Ramah Default ke Pengguna <span class="text-rose-400">*</span></label>
                        <textarea name="default_message" rows="2" placeholder="Contoh: Tagihan untuk periode ini sudah dibayar sebelumnya." required
                                  class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-indigo-500"></textarea>
                    </div>

                    <div>
                        <label class="flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox" name="is_retryable" value="1"
                                   class="rounded bg-slate-950 border-slate-700 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-xs text-slate-300">Dapat Dialihkan Otomatis ke Supplier Cadangan (Retryable / Auto-Failover)?</span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-3 pt-3 border-t border-slate-800">
                        <button type="button" @click="showAddSystemCodeModal = false" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-medium">Batal</button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold shadow-lg shadow-indigo-600/30">Simpan Standar Kode</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Modal Edit Standar Kode Internal --}}
    <div x-show="showEditSystemCodeModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="showEditSystemCodeModal = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-lg rounded-2xl bg-slate-900 border border-slate-800 p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        <span>Edit Standar Kode Error Internal</span>
                    </h3>
                    <button type="button" @click="showEditSystemCodeModal = false" class="text-slate-400 hover:text-white">✕</button>
                </div>

                <form :action="editingSystemCode.update_url" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Kode RC (2-Digit) <span class="text-rose-400">*</span></label>
                            <input type="text" name="rc" x-model="editingSystemCode.rc" required maxlength="10"
                                   class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Tipe Status Transaksi <span class="text-rose-400">*</span></label>
                            <select name="status_type" x-model="editingSystemCode.status_type" required
                                    class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                                <option value="FAILED">FAILED (Transaksi Gagal)</option>
                                <option value="PENDING">PENDING (Antrean / Menunggu)</option>
                                <option value="SUCCESS">SUCCESS (Transaksi Sukses)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Identifier Slug Internal <span class="text-rose-400">*</span></label>
                        <input type="text" name="slug" x-model="editingSystemCode.slug" required
                               class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white font-mono uppercase focus:outline-none focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Nama / Label Standar Error <span class="text-rose-400">*</span></label>
                        <input type="text" name="name" x-model="editingSystemCode.name" required
                               class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Pesan Ramah Default ke Pengguna <span class="text-rose-400">*</span></label>
                        <textarea name="default_message" x-model="editingSystemCode.default_message" rows="2" required
                                  class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-indigo-500"></textarea>
                    </div>

                    <div>
                        <label class="flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox" name="is_retryable" value="1" :checked="editingSystemCode.is_retryable"
                                   class="rounded bg-slate-950 border-slate-700 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-xs text-slate-300">Dapat Dialihkan Otomatis ke Supplier Cadangan (Retryable / Auto-Failover)?</span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-3 pt-3 border-t border-slate-800">
                        <button type="button" @click="showEditSystemCodeModal = false" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-medium">Batal</button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold shadow-lg shadow-indigo-600/30">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Modal Tambah Mapping RC Supplier --}}
    <div x-show="showAddModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="showAddModal = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-lg rounded-2xl bg-slate-900 border border-slate-800 p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Tambah Pemetaan Response Code (RC) Supplier</span>
                    </h3>
                    <button type="button" @click="showAddModal = false" class="text-slate-400 hover:text-white">✕</button>
                </div>
                <p class="text-xs text-slate-400">Petakan Response Code (RC) unik dari supplier ke standar internal sistem.</p>

                <form action="{{ route('admin.error_mappings.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Target Vendor / Supplier <span class="text-rose-400">*</span></label>
                        <select name="provider_id" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                            <option value="">Pilih Supplier...</option>
                            @foreach($providers as $p)
                                <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Response Code (RC) Upstream Supplier <span class="text-rose-400">*</span></label>
                        <input type="text" name="upstream_code" placeholder="Contoh: 000, 030, 007, 42, 55, dll" required
                               class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Kode Standar Error Internal <span class="text-rose-400">*</span></label>
                        <select name="internal_code" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                            @foreach($systemCodes as $sc)
                                <option value="{{ $sc->slug }}">RC {{ $sc->rc }} &bull; {{ $sc->slug }} ({{ $sc->name }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Pesan Khusus ke Klien / Aplikasi Mobile (Opsional)</label>
                        <input type="text" name="custom_message" placeholder="Kosongkan jika ingin memakai pesan default standar..."
                               class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox" name="is_retryable" value="1"
                                   class="rounded bg-slate-950 border-slate-700 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-xs text-slate-300">Dapat Dicoba Ulang Otomatis (Retryable Error)?</span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-3 pt-3 border-t border-slate-800">
                        <button type="button" @click="showAddModal = false" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-medium">Batal</button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold shadow-lg shadow-indigo-600/30">Simpan Pemetaan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Modal Edit Mapping RC Supplier --}}
    <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="showEditModal = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-lg rounded-2xl bg-slate-900 border border-slate-800 p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        <span>Edit Pemetaan Response Code (RC)</span>
                    </h3>
                    <button type="button" @click="showEditModal = false" class="text-slate-400 hover:text-white">✕</button>
                </div>
                <p class="text-xs text-slate-400">
                    Vendor: <strong class="text-white" x-text="editing.provider_name"></strong> &bull;
                    RC: <strong class="text-amber-400 font-mono text-sm" x-text="editing.upstream_code"></strong>
                </p>

                <form :action="editing.update_url" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Kode Standar Error Internal <span class="text-rose-400">*</span></label>
                        <select name="internal_code" x-model="editing.internal_code" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                            @foreach($systemCodes as $sc)
                                <option value="{{ $sc->slug }}">RC {{ $sc->rc }} &bull; {{ $sc->slug }} ({{ $sc->name }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Pesan Khusus ke Klien / Aplikasi Mobile (Opsional)</label>
                        <input type="text" name="custom_message" x-model="editing.custom_message" placeholder="Kosongkan jika ingin memakai pesan default standar..."
                               class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox" name="is_retryable" value="1" x-model="editing.is_retryable"
                                   class="rounded bg-slate-950 border-slate-700 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-xs text-slate-300">Dapat Dicoba Ulang Otomatis (Retryable Error)?</span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-3 pt-3 border-t border-slate-800">
                        <button type="button" @click="showEditModal = false" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-medium">Batal</button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold shadow-lg shadow-indigo-600/30">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
