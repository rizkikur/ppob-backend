@extends('admin.layouts.app')

@section('title', 'Manajemen Mitra B2B & Kredensial H2H')

@section('content')
<div class="space-y-6">
    {{-- Top Action Bar & Page Title --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
                <svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <span>Mitra Bisnis B2B (H2H)</span>
            </h1>
            <p class="text-xs text-slate-400 mt-1">Kelola akun klien API Host-to-Host, kredensial keamanan, whitelist IP, saldo deposit, dan webhook</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.partners.create') }}"
               class="px-4 py-2 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-500 hover:to-indigo-600 text-white font-bold text-xs rounded-xl transition-all shadow-lg shadow-indigo-600/30 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                <span>Tambah Mitra Baru</span>
            </a>
        </div>
    </div>

    {{-- Summary Metric Bar --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-4">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Mitra Aktif</span>
                <span class="p-1.5 bg-emerald-500/10 text-emerald-400 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="text-2xl font-black text-white mt-2">
                {{ $metrics['active_partners'] }} <span class="text-xs text-slate-400 font-normal">/ {{ $metrics['total_partners'] }}</span>
            </div>
        </div>

        <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-4">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Deposit Mitra</span>
                <span class="p-1.5 bg-indigo-500/10 text-indigo-400 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="text-2xl font-black text-indigo-400 mt-2 font-mono">
                Rp {{ number_format($metrics['total_deposit_cents'] / 100, 0, ',', '.') }}
            </div>
        </div>

        <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-4">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Traffic API Hari Ini</span>
                <span class="p-1.5 bg-blue-500/10 text-blue-400 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </span>
            </div>
            <div class="text-2xl font-black text-white mt-2 font-mono">
                {{ number_format($metrics['total_api_calls_today']) }} <span class="text-xs text-slate-400 font-normal">req</span>
            </div>
        </div>

        <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-4">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Webhook Gagal</span>
                <span class="p-1.5 {{ $metrics['failed_webhooks_count'] > 0 ? 'bg-rose-500/10 text-rose-400' : 'bg-slate-800 text-slate-400' }} rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </span>
            </div>
            <div class="text-2xl font-black {{ $metrics['failed_webhooks_count'] > 0 ? 'text-rose-400' : 'text-slate-400' }} mt-2 font-mono">
                {{ $metrics['failed_webhooks_count'] }} <span class="text-xs text-slate-400 font-normal">antrean</span>
            </div>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-4">
        <form method="GET" action="{{ route('admin.partners.index') }}" class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1 relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Cari nama mitra atau Public API Key..."
                       class="w-full bg-slate-950 border border-slate-800 rounded-xl pl-10 pr-3.5 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>

            <div class="w-full sm:w-44">
                <select name="status" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-300 focus:outline-none focus:border-indigo-500">
                    <option value="">Semua Status</option>
                    <option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="inactive" {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}>Suspended</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-xl transition-all shadow-md shadow-indigo-600/20">
                    Terapkan
                </button>
                @if(!empty($filters['search']) || !empty($filters['status']))
                    <a href="{{ route('admin.partners.index') }}"
                       class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium rounded-xl transition-colors">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Partners Table --}}
    <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        @if($partners->isEmpty())
            <div class="p-12 text-center">
                <svg class="w-12 h-12 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <h3 class="text-sm font-bold text-white mb-1">Belum Ada Mitra B2B</h3>
                <p class="text-xs text-slate-400 mb-4">Tambahkan mitra bisnis baru untuk mulai melayani transaksi H2H melalui API.</p>
                <a href="{{ route('admin.partners.create') }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl transition-all shadow-md">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    <span>Daftarkan Mitra Sekarang</span>
                </a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-900/80 text-slate-400 uppercase tracking-wider font-semibold">
                            <th class="py-3 px-4">Nama Mitra & ID</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">Public API Key</th>
                            <th class="py-3 px-4">Saldo Deposit</th>
                            <th class="py-3 px-4">Protokol & Mode</th>
                            <th class="py-3 px-4">IP Whitelist</th>
                            <th class="py-3 px-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-slate-300">
                        @foreach($partners as $partner)
                            @php
                                $balanceCents = $partner->user?->wallet?->balance_cents ?? 0;
                            @endphp
                            <tr class="hover:bg-slate-800/40 transition-colors">
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-white text-sm">
                                        <a href="{{ route('admin.partners.show', $partner) }}" class="hover:text-indigo-400 transition-colors">
                                            {{ $partner->name }}
                                        </a>
                                    </div>
                                    <div class="text-[11px] text-slate-400">ID: #{{ $partner->id }} &bull; {{ $partner->user?->email ?? 'no-email' }}</div>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium {{ $partner->is_active ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $partner->is_active ? 'bg-emerald-400' : 'bg-rose-400' }} mr-1.5"></span>
                                        {{ $partner->is_active ? 'Aktif' : 'Suspended' }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 font-mono text-[11px] text-indigo-300">
                                    <span class="bg-slate-950 px-2 py-1 rounded-lg border border-slate-800 select-all">
                                        {{ Str::limit($partner->api_key, 24) }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 font-mono font-bold text-white text-sm whitespace-nowrap">
                                    Rp {{ number_format($balanceCents / 100, 0, ',', '.') }}
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded bg-slate-800 text-[10px] uppercase font-bold text-slate-300 mr-1">{{ $partner->protocol }}</span>
                                    <span class="text-[11px] text-slate-400 uppercase font-medium">{{ $partner->response_mode }}</span>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap text-slate-400">
                                    @if(empty($partner->allowed_ips))
                                        <span class="text-amber-400 text-[11px]">Terbuka (*)</span>
                                    @else
                                        <span class="font-mono text-emerald-400 text-[11px]">{{ count($partner->allowed_ips) }} IP Aktif</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.partners.show', $partner) }}"
                                           class="px-2.5 py-1.5 bg-indigo-600/10 hover:bg-indigo-600/20 text-indigo-400 border border-indigo-500/30 rounded-xl font-medium text-xs transition-all flex items-center gap-1">
                                            <span>Kelola</span>
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        </a>

                                        <form action="{{ route('admin.partners.toggle', $partner) }}" method="POST" class="inline"
                                              onsubmit="return confirm('Apakah Anda yakin ingin mengubah status mitra {{ $partner->name }}?');">
                                            @csrf
                                            <button type="submit"
                                                    class="p-1.5 rounded-xl border {{ $partner->is_active ? 'border-rose-500/30 text-rose-400 hover:bg-rose-500/10' : 'border-emerald-500/30 text-emerald-400 hover:bg-emerald-500/10' }} transition-colors"
                                                    title="{{ $partner->is_active ? 'Suspend Mitra' : 'Aktifkan Mitra' }}">
                                                @if($partner->is_active)
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                                @else
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                @endif
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-800">
                {{ $partners->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
