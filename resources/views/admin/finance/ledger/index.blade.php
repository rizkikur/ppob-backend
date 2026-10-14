@extends('admin.layouts.app')

@section('title', 'Buku Besar & Ledger Mutasi Saldo (Double-Entry)')

@section('content')
<div class="space-y-6">
    {{-- Header & Quick Actions --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
                <svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Buku Besar Mutasi Saldo (Ledger Viewer)</span>
            </h1>
            <p class="text-xs text-slate-400 mt-1">Audit log mutasi saldo append-only (Credit / Debit) pengguna dan mitra bisnis secara immutable</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.finance.ledger.export', request()->query()) }}"
               class="px-4 py-2 bg-emerald-600/10 hover:bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 font-semibold text-xs rounded-xl transition-all flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Ekspor CSV (Streaming)</span>
            </a>

            <a href="{{ route('admin.finance.adjustments.create') }}"
               class="px-4 py-2 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-500 hover:to-indigo-600 text-white font-bold text-xs rounded-xl transition-all shadow-lg shadow-indigo-600/30 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                <span>Penyesuaian Saldo</span>
            </a>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-4">
        <form method="GET" action="{{ route('admin.finance.ledger.index') }}" class="grid grid-cols-1 sm:grid-cols-5 gap-3">
            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Cari Pengguna</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nama / email / phone..."
                       class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>

            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Jenis Mutasi</label>
                <select name="type" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-300 focus:outline-none focus:border-indigo-500">
                    <option value="">Semua (Credit / Debit)</option>
                    <option value="credit" {{ ($filters['type'] ?? '') === 'credit' ? 'selected' : '' }}>Credit (Masuk)</option>
                    <option value="debit" {{ ($filters['type'] ?? '') === 'debit' ? 'selected' : '' }}>Debit (Keluar)</option>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Referensi</label>
                <select name="reference_type" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-300 focus:outline-none focus:border-indigo-500">
                    <option value="">Semua Referensi</option>
                    <option value="topup" {{ ($filters['reference_type'] ?? '') === 'topup' ? 'selected' : '' }}>Topup Deposit</option>
                    <option value="transaction" {{ ($filters['reference_type'] ?? '') === 'transaction' ? 'selected' : '' }}>Transaksi Pembelian</option>
                    <option value="refund" {{ ($filters['reference_type'] ?? '') === 'refund' ? 'selected' : '' }}>Refund Transaksi</option>
                    <option value="adjustment" {{ ($filters['reference_type'] ?? '') === 'adjustment' ? 'selected' : '' }}>Manual Adjustment</option>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Rentang Tanggal</label>
                <input type="date" name="start_date" value="{{ $filters['start_date'] ?? '' }}"
                       class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
            </div>

            <div class="flex items-end gap-2">
                <button type="submit"
                        class="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-xl transition-all shadow-md shadow-indigo-600/20">
                    Terapkan
                </button>
                @if(!empty($filters['search']) || !empty($filters['type']) || !empty($filters['reference_type']) || !empty($filters['start_date']))
                    <a href="{{ route('admin.finance.ledger.index') }}"
                       class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium rounded-xl transition-colors">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Ledger Table --}}
    <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        @if($mutations->isEmpty())
            <div class="p-12 text-center">
                <svg class="w-12 h-12 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <h3 class="text-sm font-bold text-white mb-1">Belum Ada Catatan Mutasi</h3>
                <p class="text-xs text-slate-400">Tidak ada baris mutasi buku besar yang sesuai kriteria pencarian.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-900/80 text-slate-400 uppercase tracking-wider font-semibold">
                            <th class="py-3 px-4">ID & Waktu</th>
                            <th class="py-3 px-4">Pengguna / Mitra</th>
                            <th class="py-3 px-4">Jenis</th>
                            <th class="py-3 px-4">Nominal Mutasi</th>
                            <th class="py-3 px-4">Saldo Akhir</th>
                            <th class="py-3 px-4">Referensi</th>
                            <th class="py-3 px-4">Keterangan / Alasan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-slate-300">
                        @foreach($mutations as $m)
                            @php
                                $isCredit = $m->type === 'credit';
                                $amountCents = $m->amount_cents instanceof \App\Domain\Shared\ValueObjects\Money
                                    ? $m->amount_cents->toCents()
                                    : (int) $m->amount_cents;
                                $balanceAfterCents = $m->balance_after_cents instanceof \App\Domain\Shared\ValueObjects\Money
                                    ? $m->balance_after_cents->toCents()
                                    : (int) $m->balance_after_cents;
                            @endphp
                            <tr class="hover:bg-slate-800/40 transition-colors">
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <div class="font-mono font-medium text-white">#{{ $m->id }}</div>
                                    <div class="text-[10px] text-slate-500">{{ $m->created_at?->format('d M Y H:i:s') }}</div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-white">{{ $m->user?->name ?? 'User #' . $m->user_id }}</div>
                                    <div class="text-[11px] text-slate-400">{{ $m->user?->email ?: $m->user?->phone }}</div>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $isCredit ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' }}">
                                        {{ $isCredit ? '+ CREDIT' : '- DEBIT' }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 font-mono font-bold whitespace-nowrap text-sm {{ $isCredit ? 'text-emerald-400' : 'text-rose-400' }}">
                                    {{ $isCredit ? '+' : '-' }} Rp {{ number_format($amountCents / 100, 0, ',', '.') }}
                                </td>
                                <td class="py-3.5 px-4 font-mono font-bold text-white whitespace-nowrap">
                                    Rp {{ number_format($balanceAfterCents / 100, 0, ',', '.') }}
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded bg-slate-800 text-[10px] uppercase font-bold text-slate-300 mr-1">{{ $m->reference_type }}</span>
                                    <span class="font-mono text-[11px] text-indigo-400">#{{ $m->reference_id ?? '-' }}</span>
                                </td>
                                <td class="py-3.5 px-4 max-w-xs truncate text-[11px] text-slate-400" title="{{ $m->note }}">
                                    {{ $m->note ?: '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-800">
                {{ $mutations->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
