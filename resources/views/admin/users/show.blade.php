@extends('admin.layouts.app')

@section('title', 'Detail Pengguna ' . $user->name)

@section('content')
<div class="space-y-6">
    {{-- Header & Quick Actions --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.users.index') }}"
                   class="p-2 rounded-xl bg-slate-800 text-slate-400 hover:text-white transition-colors"
                   title="Kembali ke Daftar Pengguna">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <h1 class="text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
                    <span>{{ $user->name }}</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $user->is_active ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' }}">
                        {{ $user->is_active ? 'Aktif' : 'Suspended' }}
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $user->is_verified ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' : 'bg-slate-800 text-slate-400 border border-slate-700' }}">
                        {{ $user->is_verified ? 'KYC Terverifikasi' : 'Belum KYC' }}
                    </span>
                </h1>
            </div>
            <p class="text-xs text-slate-400 mt-1">
                ID Pengguna: <span class="font-mono text-slate-300">#{{ $user->id }}</span> &bull;
                Terdaftar: <span class="text-slate-300">{{ $user->created_at->format('d M Y H:i') }}</span> &bull;
                Handphone: <span class="font-mono text-indigo-300">{{ $user->phone }}</span>
            </p>
        </div>

        {{-- Action Buttons --}}
        <div class="flex flex-wrap items-center gap-2">
            {{-- WhatsApp Direct --}}
            @php
                $waNumber = preg_replace('/^0/', '62', preg_replace('/[^\d]/', '', $user->phone));
            @endphp
            @if($user->phone)
                <a href="https://wa.me/{{ $waNumber }}" target="_blank" rel="noopener noreferrer"
                   class="px-3 py-2 bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-300 border border-emerald-500/30 font-semibold text-xs rounded-xl transition-all flex items-center gap-1.5"
                   title="Buka WhatsApp Pengguna">
                    <svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                    <span>Hubungi WA</span>
                </a>
            @endif

            {{-- Ubah Tier Modal Trigger --}}
            <button type="button"
                    @click="$dispatch('open-tier-modal')"
                    class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-xs rounded-xl transition-all flex items-center gap-1.5">
                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                <span>Ubah Tier</span>
            </button>

            {{-- Reset PIN Trigger --}}
            <button type="button"
                    @click="$dispatch('open-pin-modal')"
                    class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-amber-300 font-semibold text-xs rounded-xl transition-all flex items-center gap-1.5">
                <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <span>Reset PIN</span>
            </button>

            {{-- Toggle KYC Form --}}
            <form action="{{ route('admin.users.toggle_kyc', $user) }}" method="POST" class="inline"
                  onsubmit="return confirm('Apakah Anda yakin ingin {{ $user->is_verified ? 'membatalkan status verifikasi KYC' : 'menyetujui verifikasi identitas (KYC)' }} pengguna {{ $user->name }}?');">
                @csrf
                <button type="submit"
                        class="px-3 py-2 {{ $user->is_verified ? 'bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 border border-blue-500/30' }} font-semibold text-xs rounded-xl transition-all flex items-center gap-1.5">
                    @if($user->is_verified)
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        <span>Cabut KYC</span>
                    @else
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Setujui KYC</span>
                    @endif
                </button>
            </form>

            {{-- Toggle Suspend Form --}}
            <form action="{{ route('admin.users.toggle', $user) }}" method="POST" class="inline"
                  onsubmit="return confirm('Apakah Anda yakin ingin {{ $user->is_active ? 'menangguhkan (suspend)' : 'membuka suspend' }} akun pengguna {{ $user->name }}?');">
                @csrf
                <button type="submit"
                        class="px-3 py-2 {{ $user->is_active ? 'bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30' : 'bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' }} font-semibold text-xs rounded-xl transition-all flex items-center gap-1.5">
                    @if($user->is_active)
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                        <span>Suspend Akun</span>
                    @else
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Buka Suspend</span>
                    @endif
                </button>
            </form>
        </div>
    </div>

    {{-- KPI & Info Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        {{-- Saldo Card --}}
        @php
            $balance = $user->wallet ? $user->wallet->balance() : \App\Domain\Shared\ValueObjects\Money::zero();
        @endphp
        <div class="bg-gradient-to-br from-indigo-950/40 via-slate-900/60 to-slate-900/60 border border-indigo-500/30 rounded-2xl p-5 shadow-xl">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-indigo-300">Saldo Dompet Pengguna</span>
                <span class="p-1.5 bg-indigo-500/20 text-indigo-400 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="text-3xl font-black text-white font-mono tracking-tight">
                {{ $balance->format() }}
            </div>
            <div class="mt-4 pt-3 border-t border-slate-800 flex items-center justify-between">
                <span class="text-xs text-slate-400">Wallet ID: #{{ $user->wallet?->id ?? '-' }}</span>
                <button type="button"
                        @click="$dispatch('open-balance-modal', { type: 'credit' })"
                        class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl transition-all shadow-md shadow-indigo-600/30 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    <span>Penyesuaian Saldo</span>
                </button>
            </div>
        </div>

        {{-- Tier Level Card --}}
        <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-5 shadow-lg">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Level Tier & Diskon</span>
                <span class="p-1.5 bg-purple-500/10 text-purple-400 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                </span>
            </div>
            <div class="text-2xl font-black text-purple-300 tracking-tight">
                {{ $user->tier?->label ?? 'Retail (Default)' }}
            </div>
            <p class="text-xs text-slate-400 mt-1">{{ $user->tier?->description ?? 'Pengguna reguler' }}</p>
            <div class="mt-4 pt-3 border-t border-slate-800 text-[11px] text-slate-400">
                Kode Tier: <span class="font-mono text-slate-200 uppercase">{{ $user->tier?->name ?? 'end_user' }}</span>
            </div>
        </div>

        {{-- Security & App Device Card --}}
        <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-5 shadow-lg">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Keamanan & Perangkat</span>
                <span class="p-1.5 bg-blue-500/10 text-blue-400 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </span>
            </div>
            <div class="space-y-1.5 text-xs">
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">PIN Transaksi:</span>
                    <span class="{{ $user->hasPin() ? 'text-emerald-400 font-bold' : 'text-amber-400 font-bold' }}">
                        {{ $user->hasPin() ? 'Sudah Diatur' : 'Belum Diatur' }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">Notifikasi Push (FCM):</span>
                    <span class="{{ $user->fcm_token ? 'text-emerald-400 font-medium' : 'text-slate-500' }}">
                        {{ $user->fcm_token ? 'Tersambung' : 'Tidak Aktif' }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">Role Sistem:</span>
                    <span class="font-mono text-slate-200 uppercase">{{ $user->role ?? 'user' }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Tables Grid (Mutations & Transactions) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Left: Wallet Mutations --}}
        <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 p-6 shadow-xl">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-800">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <span>Mutasi Saldo Dompet</span>
                </h3>
                <span class="text-xs text-slate-500">15 Mutasi Terakhir</span>
            </div>

            @php
                $mutations = $user->wallet ? $user->wallet->mutations : collect();
            @endphp

            @if($mutations->isEmpty())
                <div class="p-8 text-center bg-slate-950/40 rounded-xl border border-slate-800">
                    <p class="text-xs text-slate-400">Belum ada riwayat mutasi saldo.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-800 text-slate-400 uppercase font-semibold">
                                <th class="py-2.5 px-3">Waktu</th>
                                <th class="py-2.5 px-3">Tipe</th>
                                <th class="py-2.5 px-3 text-right">Nominal</th>
                                <th class="py-2.5 px-3 text-right">Saldo Akhir</th>
                                <th class="py-2.5 px-3">Catatan / Ref</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800 text-slate-300">
                            @foreach($mutations as $m)
                                <tr class="hover:bg-slate-800/40">
                                    <td class="py-2 px-3 whitespace-nowrap text-slate-400 text-[11px]">
                                        {{ $m->created_at->format('d M H:i') }}
                                    </td>
                                    <td class="py-2 px-3 whitespace-nowrap">
                                        @if($m->type === 'credit')
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400">
                                                + CREDIT
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-500/10 text-rose-400">
                                                - DEBIT
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3 text-right whitespace-nowrap font-mono font-bold {{ $m->type === 'credit' ? 'text-emerald-400' : 'text-rose-400' }}">
                                        {{ $m->amount_cents ? $m->amount_cents->format() : 'Rp 0' }}
                                    </td>
                                    <td class="py-2 px-3 text-right whitespace-nowrap font-mono text-slate-300">
                                        {{ $m->balance_after_cents ? $m->balance_after_cents->format() : 'Rp 0' }}
                                    </td>
                                    <td class="py-2 px-3 text-[11px] text-slate-400 truncate max-w-xs" title="{{ $m->description }}">
                                        {{ $m->description ?: ($m->reference_type ? "Ref: {$m->reference_type} #{$m->reference_id}" : '-') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Right: Recent PPOB Transactions --}}
        <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 p-6 shadow-xl">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-800">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span>Transaksi PPOB Terakhir</span>
                </h3>
                <span class="text-xs text-slate-500">15 Transaksi Terakhir</span>
            </div>

            @if($user->transactions->isEmpty())
                <div class="p-8 text-center bg-slate-950/40 rounded-xl border border-slate-800">
                    <p class="text-xs text-slate-400">Pengguna belum melakukan transaksi PPOB.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-800 text-slate-400 uppercase font-semibold">
                                <th class="py-2.5 px-3">Trx ID</th>
                                <th class="py-2.5 px-3">Produk & Tujuan</th>
                                <th class="py-2.5 px-3 text-right">Harga Jual</th>
                                <th class="py-2.5 px-3 text-center">Status</th>
                                <th class="py-2.5 px-3">Waktu</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800 text-slate-300">
                            @foreach($user->transactions as $trx)
                                @php
                                    $statusBadge = match($trx->status) {
                                        'success' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                        'failed' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                                        'processing' => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
                                        default => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                    };
                                @endphp
                                <tr class="hover:bg-slate-800/40">
                                    <td class="py-2 px-3 whitespace-nowrap font-mono text-indigo-400 font-bold">
                                        <a href="{{ route('admin.transactions.show', $trx) }}" class="hover:underline">
                                            #{{ $trx->id }}
                                        </a>
                                    </td>
                                    <td class="py-2 px-3">
                                        <div class="font-bold text-white text-xs">{{ $trx->product?->name ?? 'SKU #'.$trx->product_id }}</div>
                                        <div class="font-mono text-[11px] text-slate-400">{{ $trx->customer_number }}</div>
                                    </td>
                                    <td class="py-2 px-3 text-right whitespace-nowrap font-mono font-bold text-white">
                                        {{ $trx->sell_price_cents ? $trx->sell_price_cents->format() : 'Rp 0' }}
                                    </td>
                                    <td class="py-2 px-3 text-center whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold border {{ $statusBadge }}">
                                            {{ strtoupper($trx->status) }}
                                        </span>
                                    </td>
                                    <td class="py-2 px-3 whitespace-nowrap text-slate-400 text-[11px]">
                                        {{ $trx->created_at->format('d M H:i') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Modals --}}
    @include('admin.users.modals._adjust_balance_modal')
    @include('admin.users.modals._update_tier_modal')
    @include('admin.users.modals._reset_pin_modal')
</div>
@endsection
