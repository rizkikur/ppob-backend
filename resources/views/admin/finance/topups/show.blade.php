@extends('admin.layouts.app')

@section('title', 'Detail Permohonan Top-Up #' . $topup->id)

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.finance.topups.index') }}"
               class="p-2 rounded-xl bg-slate-800 text-slate-400 hover:text-white transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
                    <span>Tiket Top-Up #{{ $topup->id }}</span>
                    @php
                        $statusBadge = match($topup->status) {
                            'paid' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                            'failed' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                            default => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                        };
                        $statusLabel = match($topup->status) {
                            'paid' => 'Disetujui (Paid)',
                            'failed' => 'Ditolak (Failed)',
                            default => 'Pending Verifikasi',
                        };
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $statusBadge }}">
                        {{ $statusLabel }}
                    </span>
                </h1>
                <p class="text-xs text-slate-400 mt-1">Dibuat pada {{ $topup->created_at->format('d M Y H:i:s') }}</p>
            </div>
        </div>
    </div>

    {{-- Topup Info Grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        {{-- Amount & Method Card --}}
        <div class="bg-gradient-to-br from-indigo-950/40 via-slate-900/60 to-slate-900/60 border border-indigo-500/30 rounded-2xl p-6 shadow-xl space-y-4">
            <div>
                <span class="text-xs font-semibold uppercase tracking-wider text-indigo-300">Nominal Deposit</span>
                <div class="text-3xl font-black text-white font-mono mt-1">
                    Rp {{ number_format($topup->amount_cents->toCents() / 100, 0, ',', '.') }}
                </div>
            </div>

            <div class="pt-3 border-t border-slate-800 space-y-2 text-xs">
                <div class="flex justify-between">
                    <span class="text-slate-400">Metode:</span>
                    <span class="font-bold text-white uppercase">{{ $topup->method }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Gateway / Bank:</span>
                    <span class="text-slate-200">{{ $topup->payment_gateway ?: 'Manual Bank Transfer' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">ID Referensi:</span>
                    <span class="font-mono text-indigo-300">{{ $topup->gateway_ref ?: '-' }}</span>
                </div>
                @if($topup->confirmed_at)
                    <div class="flex justify-between">
                        <span class="text-slate-400">Dikonfirmasi Pada:</span>
                        <span class="text-emerald-400">{{ $topup->confirmed_at->format('d M Y H:i:s') }}</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- User Info Card --}}
        <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-6 space-y-4">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Informasi Pengguna</h3>
            <div>
                <div class="text-lg font-bold text-white">{{ $topup->user?->name }}</div>
                <div class="text-xs text-slate-400 mt-0.5">{{ $topup->user?->email ?: $topup->user?->phone }}</div>
                <div class="mt-2 inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-slate-300 uppercase">
                    Role: {{ $topup->user?->role ?? 'user' }}
                </div>
            </div>

            <div class="pt-3 border-t border-slate-800 flex items-center justify-between text-xs">
                <span class="text-slate-400">Saldo Dompet Saat Ini:</span>
                <span class="font-mono font-bold text-emerald-400 text-sm">
                    {{ $topup->user?->wallet ? $topup->user->wallet->balance()->format() : 'Rp 0' }}
                </span>
            </div>
        </div>
    </div>

    {{-- Actions for Pending Request --}}
    @if($topup->status === 'pending')
        <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-6">
            <h3 class="text-sm font-bold text-white uppercase tracking-wider text-indigo-400 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Tindakan Approval Admin</span>
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Approve Form --}}
                <form action="{{ route('admin.finance.topups.approve', $topup) }}" method="POST"
                      class="p-4 bg-emerald-500/5 border border-emerald-500/20 rounded-xl space-y-3">
                    @csrf
                    <div>
                        <h4 class="text-sm font-bold text-emerald-400">Setujui Tiket Top-Up</h4>
                        <p class="text-xs text-slate-400 mt-0.5">Saldo dompet akan langsung bertambah sebesar nominal di atas.</p>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Catatan Tambahan (Opsional)</label>
                        <input type="text" name="notes" placeholder="Contoh: Bukti mutasi BCA terverifikasi"
                               class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500">
                    </div>
                    <button type="submit"
                            class="w-full py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl transition-all shadow-md shadow-emerald-600/30">
                        Setujui & Tambah Saldo Sekarang
                    </button>
                </form>

                {{-- Reject Form --}}
                <form action="{{ route('admin.finance.topups.reject', $topup) }}" method="POST"
                      class="p-4 bg-rose-500/5 border border-rose-500/20 rounded-xl space-y-3">
                    @csrf
                    <div>
                        <h4 class="text-sm font-bold text-rose-400">Tolak Tiket Top-Up</h4>
                        <p class="text-xs text-slate-400 mt-0.5">Tiket akan digagalkan dan alasan penolakan dicatat di audit log.</p>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Alasan Penolakan <span class="text-rose-400">*</span></label>
                        <textarea name="reason" rows="1" required placeholder="Contoh: Dana belum masuk rekening bank tujuan"
                                  class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-rose-500"></textarea>
                    </div>
                    <button type="submit"
                            class="w-full py-2 bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs rounded-xl transition-all shadow-md shadow-rose-600/30">
                        Tolak Tiket Ini
                    </button>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
