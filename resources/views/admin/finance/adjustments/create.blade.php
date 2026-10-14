@extends('admin.layouts.app')

@section('title', 'Penyesuaian Saldo Manual (Credit / Debit)')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
                <svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Penyesuaian Saldo Manual (Intervensi Finansial)</span>
            </h1>
            <p class="text-xs text-slate-400 mt-1">Intervensi saldo pengguna/mitra langsung via WalletService dengan kepatuhan audit log penuh</p>
        </div>

        <a href="{{ route('admin.finance.ledger.index') }}"
           class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-xs rounded-xl transition-colors flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>Buku Besar Ledger</span>
        </a>
    </div>

    {{-- Strict Financial Notice --}}
    <div class="p-4 bg-amber-500/10 border border-amber-500/30 rounded-2xl text-xs text-amber-300 flex items-start gap-3">
        <svg class="w-5 h-5 text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <div>
            <span class="font-bold text-amber-200 uppercase tracking-wider">Aturan Ketat Finansial:</span>
            Seluruh penyesuaian saldo manual akan diverifikasi dengan konfirmasi password admin, dicatat ke dalam log audit (`admin_audit_logs`), serta menghasilkan mutasi append-only di buku besar tanpa bisa diubah atau dihapus.
        </div>
    </div>

    {{-- Form --}}
    <form action="{{ route('admin.finance.adjustments.store') }}" method="POST"
          x-data="{ type: 'credit' }"
          class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-6 space-y-6">
        @csrf

        {{-- 1. Select User / Partner --}}
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Pilih Pengguna atau Mitra B2B <span class="text-rose-400">*</span></label>
            <select name="user_id" required
                    class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                <option value="">-- Pilih Akun Penerima Penyesuaian Saldo --</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ (old('user_id') == $u->id || ($selectedUser && $selectedUser->id == $u->id)) ? 'selected' : '' }}>
                        {{ $u->name }} ({{ $u->email ?: $u->phone }}) &bull; Saldo Saat Ini: Rp {{ number_format(($u->wallet?->balance_cents ?? 0) / 100, 0, ',', '.') }}
                    </option>
                @endforeach
            </select>
            @error('user_id') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- 2. Adjustment Type Selection --}}
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Jenis Penyesuaian <span class="text-rose-400">*</span></label>
            <div class="grid grid-cols-2 gap-3">
                <label class="relative flex items-center p-3.5 rounded-xl border cursor-pointer transition-all"
                       :class="type === 'credit' ? 'bg-emerald-500/10 border-emerald-500 text-emerald-300' : 'bg-slate-950 border-slate-800 text-slate-400 hover:border-slate-700'">
                    <input type="radio" name="type" value="credit" x-model="type" class="sr-only">
                    <div class="flex items-center gap-2.5">
                        <div class="w-4 h-4 rounded-full border flex items-center justify-center"
                             :class="type === 'credit' ? 'border-emerald-400 bg-emerald-400' : 'border-slate-600'">
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-950" x-show="type === 'credit'"></span>
                        </div>
                        <div>
                            <div class="text-xs font-bold text-white">Penambahan Saldo (Credit)</div>
                            <div class="text-[11px] text-slate-400">Kompensasi selisih / topup manual</div>
                        </div>
                    </div>
                </label>

                <label class="relative flex items-center p-3.5 rounded-xl border cursor-pointer transition-all"
                       :class="type === 'debit' ? 'bg-rose-500/10 border-rose-500 text-rose-300' : 'bg-slate-950 border-slate-800 text-slate-400 hover:border-slate-700'">
                    <input type="radio" name="type" value="debit" x-model="type" class="sr-only">
                    <div class="flex items-center gap-2.5">
                        <div class="w-4 h-4 rounded-full border flex items-center justify-center"
                             :class="type === 'debit' ? 'border-rose-400 bg-rose-400' : 'border-slate-600'">
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-950" x-show="type === 'debit'"></span>
                        </div>
                        <div>
                            <div class="text-xs font-bold text-white">Pemotongan Saldo (Debit)</div>
                            <div class="text-[11px] text-slate-400">Koreksi kelebihan / penarikan dana</div>
                        </div>
                    </div>
                </label>
            </div>
            @error('type') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- 3. Amount --}}
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Nominal Penyesuaian (Rp) <span class="text-rose-400">*</span></label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-sm">Rp</div>
                <input type="number" name="amount" value="{{ old('amount') }}" min="1" step="1" required placeholder="Contoh: 250000"
                       class="w-full bg-slate-950 border border-slate-800 rounded-xl pl-10 pr-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>
            @error('amount') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- 4. Audit Reason (min 15 chars) --}}
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Alasan Penyesuaian Lengkap (Min 15 Karakter) <span class="text-rose-400">*</span></label>
            <textarea name="reason" rows="3" required placeholder="Jelaskan dasar penyesuaian saldo (contoh: Koreksi selisih invoice transaksi gagal #12345 berdasarkan tiket CS-992)"
                      class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">{{ old('reason') }}</textarea>
            @error('reason') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Submit --}}
        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
            <a href="{{ route('admin.finance.ledger.index') }}"
               class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-xs rounded-xl transition-colors">
                Batal
            </a>
            <button type="submit"
                    :class="type === 'credit' ? 'bg-emerald-600 hover:bg-emerald-500 shadow-emerald-600/30' : 'bg-rose-600 hover:bg-rose-500 shadow-rose-600/30'"
                    class="px-6 py-2.5 text-white font-bold text-xs rounded-xl transition-all shadow-lg flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <span>Konfirmasi & Eksekusi Penyesuaian</span>
            </button>
        </div>
    </form>
</div>
@endsection
