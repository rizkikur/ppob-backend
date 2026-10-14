@extends('admin.layouts.app')

@section('title', 'Rekonsiliasi Saldo Deposit Provider Upstream')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
                <svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                <span>Rekonsiliasi Saldo Deposit Provider</span>
            </h1>
            <p class="text-xs text-slate-400 mt-1">Audit perbandingan saldo deposit pembukuan internal vs sisa saldo real-time di API upstream</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.providers.index') }}"
               class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-xs rounded-xl transition-colors flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <span>Status Circuit Breaker</span>
            </a>
        </div>
    </div>

    {{-- Reconciliation Table --}}
    <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-800 bg-slate-900/80 text-slate-400 uppercase tracking-wider font-semibold">
                        <th class="py-3.5 px-4">Provider Upstream</th>
                        <th class="py-3.5 px-4">Status Driver</th>
                        <th class="py-3.5 px-4">Saldo Tercatat Internal</th>
                        <th class="py-3.5 px-4">Saldo Aktual Upstream</th>
                        <th class="py-3.5 px-4">Selisih (Discrepancy)</th>
                        <th class="py-3.5 px-4">Hasil Rekon</th>
                        <th class="py-3.5 px-4 text-right">Terakhir Dicek</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-slate-300">
                    @forelse($reconciliations as $item)
                        @php
                            $isMatch = $item['status'] === 'MATCH';
                            $statusBadge = $isMatch
                                ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                                : 'bg-rose-500/10 text-rose-400 border-rose-500/20';
                            $statusText = $isMatch ? 'MATCH (Sesuai)' : 'DISCREPANCY (Ada Selisih)';
                        @endphp
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="py-4 px-4">
                                <div class="font-bold text-white text-sm">{{ $item['name'] }}</div>
                                <div class="font-mono text-[11px] text-indigo-400">{{ $item['code'] }}</div>
                            </td>
                            <td class="py-4 px-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $item['is_active'] ? 'bg-emerald-500/10 text-emerald-400' : 'bg-slate-700 text-slate-400' }}">
                                    {{ $item['is_active'] ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="py-4 px-4 font-mono font-bold text-white whitespace-nowrap text-sm">
                                Rp {{ number_format($item['internal_balance_cents'] / 100, 0, ',', '.') }}
                            </td>
                            <td class="py-4 px-4 font-mono font-bold text-indigo-300 whitespace-nowrap text-sm">
                                Rp {{ number_format($item['upstream_balance_cents'] / 100, 0, ',', '.') }}
                            </td>
                            <td class="py-4 px-4 font-mono font-bold whitespace-nowrap text-sm">
                                @if($item['discrepancy_cents'] === 0)
                                    <span class="text-emerald-400">Rp 0</span>
                                @else
                                    <span class="text-rose-400">
                                        {{ $item['discrepancy_cents'] > 0 ? '+' : '' }} Rp {{ number_format($item['discrepancy_cents'] / 100, 0, ',', '.') }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium border {{ $statusBadge }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $isMatch ? 'bg-emerald-400' : 'bg-rose-400' }} mr-1.5"></span>
                                    {{ $statusText }}
                                </span>
                            </td>
                            <td class="py-4 px-4 text-right whitespace-nowrap text-slate-400 font-mono text-[11px]">
                                {{ $item['last_checked_at'] }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400">
                                Tidak ada provider upstream yang terdaftar.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
