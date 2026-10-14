@extends('admin.layouts.app')

@section('title', 'Detail Mitra ' . $partner->name)

@section('content')
<div class="space-y-6">
    {{-- Header & Quick Actions --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.partners.index') }}"
                   class="p-2 rounded-xl bg-slate-800 text-slate-400 hover:text-white transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <h1 class="text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
                    <span>{{ $partner->name }}</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $partner->is_active ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' }}">
                        {{ $partner->is_active ? 'Aktif' : 'Suspended' }}
                    </span>
                </h1>
            </div>
            <p class="text-xs text-slate-400 mt-1">
                ID Mitra: <span class="font-mono text-slate-300">#{{ $partner->id }}</span> &bull;
                Terdaftar: <span class="text-slate-300">{{ $partner->created_at->format('d M Y H:i') }}</span>
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.partners.edit', $partner) }}"
               class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-xs rounded-xl transition-all flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                <span>Edit Konfigurasi</span>
            </a>

            <form action="{{ route('admin.partners.toggle', $partner) }}" method="POST" class="inline"
                  onsubmit="return confirm('Apakah Anda yakin ingin {{ $partner->is_active ? 'menangguhkan (suspend)' : 'mengaktifkan kembali' }} mitra {{ $partner->name }}?');">
                @csrf
                <button type="submit"
                        class="px-3.5 py-2 {{ $partner->is_active ? 'bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30' : 'bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' }} font-semibold text-xs rounded-xl transition-all flex items-center gap-1.5">
                    @if($partner->is_active)
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                        <span>Suspend Mitra</span>
                    @else
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Aktifkan Kembali</span>
                    @endif
                </button>
            </form>
        </div>
    </div>

    {{-- Quick KPI Cards (Saldo Deposit, API Traffic, Webhooks) --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        {{-- Saldo Card with Adjustment Trigger --}}
        <div class="bg-gradient-to-br from-indigo-950/40 via-slate-900/60 to-slate-900/60 border border-indigo-500/30 rounded-2xl p-5 shadow-xl">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-indigo-300">Saldo Deposit Mitra</span>
                <span class="p-1.5 bg-indigo-500/20 text-indigo-400 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="text-3xl font-black text-white font-mono tracking-tight">
                Rp {{ number_format($balance->toCents() / 100, 0, ',', '.') }}
            </div>
            <div class="mt-4 pt-3 border-t border-slate-800 flex items-center justify-between">
                <span class="text-xs text-slate-400">User Wallet ID: #{{ $partnerUser->id }}</span>
                <button type="button"
                        @click="$dispatch('open-balance-modal', { type: 'credit' })"
                        class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl transition-all shadow-md shadow-indigo-600/30 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    <span>Penyesuaian Saldo</span>
                </button>
            </div>
        </div>

        {{-- API Calls Card --}}
        <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-5">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total API Traffic</span>
                <span class="p-1.5 bg-blue-500/10 text-blue-400 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </span>
            </div>
            <div class="text-3xl font-black text-white font-mono tracking-tight">
                {{ number_format($partner->logs->count()) }} <span class="text-xs text-slate-400 font-normal">request tersimpan</span>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-800 text-xs text-slate-400">
                Rate Limit: <strong class="text-slate-200">{{ $partner->rate_limit_rpm }} req/mnt</strong>
            </div>
        </div>

        {{-- Webhooks Card --}}
        <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-5">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Status Callback Webhook</span>
                <span class="p-1.5 bg-purple-500/10 text-purple-400 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                </span>
            </div>
            <div class="text-3xl font-black text-white font-mono tracking-tight">
                {{ number_format($partner->webhookDeliveries->count()) }} <span class="text-xs text-slate-400 font-normal">notifikasi</span>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-800 text-xs text-slate-400 truncate" title="{{ $partner->callback_url ?: 'Belum diset' }}">
                Endpoint: <span class="font-mono text-indigo-400">{{ $partner->callback_url ? Str::limit($partner->callback_url, 30) : 'Belum diisi' }}</span>
            </div>
        </div>
    </div>

    {{-- Main Grid Content --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Left Column: API Credentials & IP Whitelist --}}
        <div class="space-y-6">
            @include('admin.partners.components._api_credential_card')
            @include('admin.partners.components._ip_whitelist_manager')
        </div>

        {{-- Right Column: Custom Pricing & Webhook Retry --}}
        <div class="space-y-6">
            @include('admin.partners.components._partner_pricing_table')
            @include('admin.partners.components._webhook_delivery_logs')
        </div>
    </div>

    {{-- Bottom Full-Width: API Traffic Logs --}}
    <div>
        @include('admin.partners.components._partner_api_logs')
    </div>

    {{-- Modals --}}
    @include('admin.partners.modals._secret_revealed_modal')
    @include('admin.partners.modals._confirm_rotate_modal')
    @include('admin.partners.modals._balance_adjustment_modal')
    @include('admin.partners.modals._custom_pricing_modal')
</div>
@endsection
