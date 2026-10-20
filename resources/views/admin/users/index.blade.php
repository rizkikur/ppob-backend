@extends('admin.layouts.app')

@section('title', 'Manajemen Pengguna Mobile Apps')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
                <svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                <span>Pengguna Mobile Apps (Retail / Agen)</span>
            </h1>
            <p class="text-xs text-slate-400 mt-1">Kelola akun pelanggan aplikasi Android/iOS, status verifikasi identitas (KYC), tier harga, dan saldo dompet</p>
        </div>
    </div>

    {{-- Summary KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Users --}}
        <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-5 shadow-lg">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Pengguna</span>
                <span class="p-1.5 bg-indigo-500/10 text-indigo-400 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </span>
            </div>
            <div class="text-2xl font-black text-white font-mono tracking-tight">
                {{ number_format($metrics['total_users']) }}
            </div>
            <p class="text-[11px] text-slate-500 mt-1">Akun terdaftar di aplikasi</p>
        </div>

        {{-- Active Users --}}
        <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-5 shadow-lg">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Pengguna Aktif</span>
                <span class="p-1.5 bg-emerald-500/10 text-emerald-400 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="text-2xl font-black text-emerald-400 font-mono tracking-tight">
                {{ number_format($metrics['active_users']) }}
            </div>
            <p class="text-[11px] text-slate-500 mt-1">Dapat bertransaksi normal</p>
        </div>

        {{-- Verified KYC --}}
        <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-5 shadow-lg">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Terverifikasi (KYC)</span>
                <span class="p-1.5 bg-blue-500/10 text-blue-400 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </span>
            </div>
            <div class="text-2xl font-black text-blue-400 font-mono tracking-tight">
                {{ number_format($metrics['verified_users']) }}
            </div>
            <p class="text-[11px] text-slate-500 mt-1">Identitas terverifikasi valid</p>
        </div>

        {{-- Total Saldo Pengguna --}}
        <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-5 shadow-lg">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Saldo Pengguna</span>
                <span class="p-1.5 bg-amber-500/10 text-amber-400 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="text-2xl font-black text-amber-400 font-mono tracking-tight">
                Rp {{ number_format($metrics['total_balance_cents'] / 100, 0, ',', '.') }}
            </div>
            <p class="text-[11px] text-slate-500 mt-1">Dana mengendap di dompet</p>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 p-4 sm:p-5 shadow-xl">
        <form method="GET" action="{{ route('admin.users.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
            {{-- Search Input --}}
            <div class="lg:col-span-2">
                <label for="search" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Pencarian</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text"
                           id="search"
                           name="search"
                           value="{{ request('search', $filters['search'] ?? '') }}"
                           placeholder="Cari nama, no handphone, email..."
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl pl-9 pr-3.5 py-2 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                </div>
            </div>

            {{-- Tier Filter --}}
            <div>
                <label for="user_tier_id" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Tier Level</label>
                <select id="user_tier_id"
                        name="user_tier_id"
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                    <option value="">Semua Tier</option>
                    @foreach($tiers as $tier)
                        @if($tier->name !== 'partner')
                            <option value="{{ $tier->id }}" @selected((string)request('user_tier_id', $filters['user_tier_id'] ?? '') === (string)$tier->id)>
                                {{ $tier->label }}
                            </option>
                        @endif
                    @endforeach
                </select>
            </div>

            {{-- KYC Status --}}
            <div>
                <label for="is_verified" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Status KYC</label>
                <select id="is_verified"
                        name="is_verified"
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                    <option value="">Semua Status KYC</option>
                    <option value="1" @selected(request('is_verified') === '1')>Terverifikasi</option>
                    <option value="0" @selected(request('is_verified') === '0')>Belum Verifikasi</option>
                </select>
            </div>

            {{-- Submit & Reset --}}
            <div class="flex items-end gap-2">
                <button type="submit"
                        class="w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl shadow-lg shadow-indigo-600/30 transition-all">
                    Terapkan
                </button>
                @if(request()->hasAny(['search', 'user_tier_id', 'is_active', 'is_verified']))
                    <a href="{{ route('admin.users.index') }}"
                       class="px-3 py-2 text-xs font-semibold text-slate-400 hover:text-white bg-slate-800 hover:bg-slate-700 rounded-xl transition-all"
                       title="Reset Filter">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Users Table --}}
    <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-800 bg-slate-950/40 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                        <th class="py-3.5 px-4">Pengguna & Kontak</th>
                        <th class="py-3.5 px-4 text-center">Tier Level</th>
                        <th class="py-3.5 px-4 text-right">Saldo Dompet</th>
                        <th class="py-3.5 px-4 text-center">Status KYC</th>
                        <th class="py-3.5 px-4 text-center">Status Akun</th>
                        <th class="py-3.5 px-4 text-center">Trx</th>
                        <th class="py-3.5 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 text-sm">
                    @forelse($users as $user)
                        @php
                            $waNumber = preg_replace('/^0/', '62', preg_replace('/[^\d]/', '', $user->phone));
                            $balance = $user->wallet ? $user->wallet->balance() : \App\Domain\Shared\ValueObjects\Money::zero();
                        @endphp
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            {{-- Name & Contact --}}
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-white text-xs">{{ $user->name }}</div>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="font-mono text-[11px] text-slate-400">{{ $user->phone }}</span>
                                    @if($user->phone)
                                        <a href="https://wa.me/{{ $waNumber }}" target="_blank" rel="noopener noreferrer"
                                           class="inline-flex items-center text-emerald-400 hover:text-emerald-300"
                                           title="Chat via WhatsApp">
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                                        </a>
                                    @endif
                                </div>
                                @if($user->email)
                                    <div class="text-[11px] text-slate-500 truncate max-w-xs">{{ $user->email }}</div>
                                @endif
                            </td>

                            {{-- Tier Level --}}
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold uppercase bg-slate-800 text-indigo-300 border border-indigo-500/20">
                                    {{ $user->tier?->label ?? 'Retail' }}
                                </span>
                            </td>

                            {{-- Wallet Balance --}}
                            <td class="py-3.5 px-4 text-right whitespace-nowrap font-mono text-xs font-bold text-slate-100">
                                {{ $balance->format() }}
                            </td>

                            {{-- KYC Status --}}
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                @if($user->is_verified)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-500/10 text-blue-400 border border-blue-500/20">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <span>Terverifikasi</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-medium bg-slate-800 text-slate-400 border border-slate-700">
                                        Belum KYC
                                    </span>
                                @endif
                            </td>

                            {{-- Status Akun --}}
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                @if($user->is_active)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                        <span>Aktif</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                        <span>Suspended</span>
                                    </span>
                                @endif
                            </td>

                            {{-- Total Transactions --}}
                            <td class="py-3.5 px-4 text-center whitespace-nowrap font-mono text-xs text-slate-300">
                                {{ number_format($user->transactions_count) }}
                            </td>

                            {{-- Action --}}
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <a href="{{ route('admin.users.show', $user) }}"
                                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-800 hover:bg-indigo-600 text-slate-200 hover:text-white rounded-xl text-xs font-semibold transition-all">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <span>Kelola</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-500 text-xs">
                                Tidak ada data pengguna mobile yang cocok dengan kriteria filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
