@extends('admin.layouts.app')

@section('title', 'Persetujuan Top-Up Manual & Verifikasi Deposit')

@section('content')
<div class="space-y-6">
    {{-- Top Action Bar & Page Title --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
                <svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Persetujuan Top-Up Saldo</span>
            </h1>
            <p class="text-xs text-slate-400 mt-1">Verifikasi bukti transfer deposit bank manual dan persetujuan penambahan saldo dompet pengguna/mitra</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.finance.adjustments.create') }}"
               class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-xs rounded-xl transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                <span>Penyesuaian Saldo Manual</span>
            </a>
        </div>
    </div>

    {{-- Summary Metric Bar --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-4">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Antrean Pending</span>
                <span class="p-1.5 {{ $metrics['pending_count'] > 0 ? 'bg-amber-500/10 text-amber-400' : 'bg-slate-800 text-slate-400' }} rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="text-2xl font-black text-white mt-2 font-mono">
                {{ $metrics['pending_count'] }} <span class="text-xs text-slate-400 font-normal">tiket menunggu</span>
            </div>
        </div>

        <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-4">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Nominal Pending</span>
                <span class="p-1.5 bg-amber-500/10 text-amber-400 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="text-2xl font-black text-amber-400 mt-2 font-mono">
                Rp {{ number_format($metrics['pending_amount_cents'] / 100, 0, ',', '.') }}
            </div>
        </div>

        <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-4">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Disetujui Hari Ini</span>
                <span class="p-1.5 bg-emerald-500/10 text-emerald-400 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </span>
            </div>
            <div class="text-2xl font-black text-emerald-400 mt-2 font-mono">
                Rp {{ number_format($metrics['today_approved_amount_cents'] / 100, 0, ',', '.') }}
            </div>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-4">
        <form method="GET" action="{{ route('admin.finance.topups.index') }}" class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1 relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Cari nama pengguna, email, nomor HP, atau ID referensi gateway..."
                       class="w-full bg-slate-950 border border-slate-800 rounded-xl pl-10 pr-3.5 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>

            <div class="w-full sm:w-48">
                <select name="status" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-300 focus:outline-none focus:border-indigo-500">
                    <option value="">Semua Status</option>
                    <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pending (Menunggu)</option>
                    <option value="paid" {{ ($filters['status'] ?? '') === 'paid' ? 'selected' : '' }}>Paid / Disetujui</option>
                    <option value="failed" {{ ($filters['status'] ?? '') === 'failed' ? 'selected' : '' }}>Failed / Ditolak</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-xl transition-all shadow-md shadow-indigo-600/20">
                    Filter
                </button>
                @if(!empty($filters['search']) || !empty($filters['status']))
                    <a href="{{ route('admin.finance.topups.index') }}"
                       class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium rounded-xl transition-colors">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Topup Requests Table --}}
    <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl overflow-hidden shadow-xl"
         x-data="{
            rejectModalOpen: false,
            approveModalOpen: false,
            selectedTopupId: null,
            selectedAmount: '',
            selectedUserName: ''
         }">
        @if($topups->isEmpty())
            <div class="p-12 text-center">
                <svg class="w-12 h-12 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <h3 class="text-sm font-bold text-white mb-1">Tidak Ada Permintaan Top-Up</h3>
                <p class="text-xs text-slate-400">Tidak ada tiket top-up yang cocok dengan filter yang dipilih.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-900/80 text-slate-400 uppercase tracking-wider font-semibold">
                            <th class="py-3 px-4">ID & Waktu</th>
                            <th class="py-3 px-4">Pengguna / Mitra</th>
                            <th class="py-3 px-4">Nominal Top-Up</th>
                            <th class="py-3 px-4">Metode Pembayaran</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-slate-300">
                        @foreach($topups as $topup)
                            @php
                                $statusBadge = match($topup->status) {
                                    'paid' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                    'failed' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                                    default => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                };
                                $statusLabel = match($topup->status) {
                                    'paid' => 'Disetujui (Paid)',
                                    'failed' => 'Ditolak / Failed',
                                    default => 'Menunggu Approval',
                                };
                            @endphp
                            <tr class="hover:bg-slate-800/40 transition-colors">
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <div class="font-mono font-bold text-white">#{{ $topup->id }}</div>
                                    <div class="text-[11px] text-slate-500">{{ $topup->created_at->format('d M Y H:i') }}</div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-white text-sm">{{ $topup->user?->name ?? 'User #' . $topup->user_id }}</div>
                                    <div class="text-[11px] text-slate-400">{{ $topup->user?->email ?: $topup->user?->phone }}</div>
                                </td>
                                <td class="py-3.5 px-4 font-mono font-bold text-white text-sm whitespace-nowrap">
                                    Rp {{ number_format($topup->amount_cents->toCents() / 100, 0, ',', '.') }}
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded bg-slate-800 text-[10px] uppercase font-bold text-slate-300 mr-1">{{ $topup->method }}</span>
                                    <span class="text-[11px] text-slate-400">{{ $topup->payment_gateway ?? 'Manual Bank' }}</span>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium border {{ $statusBadge }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.finance.topups.show', $topup) }}"
                                           class="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl font-medium text-xs transition-colors">
                                            Detail
                                        </a>

                                        @if($topup->status === 'pending')
                                            {{-- Approve Form --}}
                                            <form action="{{ route('admin.finance.topups.approve', $topup) }}" method="POST" class="inline"
                                                  onsubmit="return confirm('Apakah Anda yakin ingin menyetujui tiket top-up #{{ $topup->id }} sebesar Rp {{ number_format($topup->amount_cents->toCents() / 100, 0, ',', '.') }}?');">
                                                @csrf
                                                <button type="submit"
                                                        class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl transition-all shadow-md shadow-emerald-600/30 flex items-center gap-1">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                    <span>Setujui</span>
                                                </button>
                                            </form>

                                            {{-- Reject Trigger --}}
                                            <button type="button"
                                                    @click="rejectModalOpen = true; selectedTopupId = {{ $topup->id }}; selectedAmount = 'Rp {{ number_format($topup->amount_cents->toCents() / 100, 0, ',', '.') }}'; selectedUserName = '{{ addslashes($topup->user?->name) }}'"
                                                    class="px-2.5 py-1.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 font-medium text-xs rounded-xl transition-colors">
                                                Tolak
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-800">
                {{ $topups->links() }}
            </div>
        @endif

        {{-- Reject Modal --}}
        <div x-show="rejectModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="rejectModalOpen = false"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                <div class="relative inline-block align-bottom bg-slate-900 border border-rose-500/30 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full p-6 text-white">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="p-3 bg-rose-500/10 border border-rose-500/30 rounded-xl text-rose-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-white">Tolak Permohonan Top-Up</h3>
                            <p class="text-xs text-slate-400" x-text="selectedUserName + ' &bull; ' + selectedAmount"></p>
                        </div>
                    </div>

                    <form :action="'{{ url(config('admin.prefix', 'office') . '/finance/topups') }}/' + selectedTopupId + '/reject'" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Alasan Penolakan <span class="text-rose-400">*</span></label>
                            <textarea name="reason" rows="3" required placeholder="Contoh: Bukti transfer tidak terbaca / dana belum masuk mutasi rekening BCA..."
                                      class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-rose-500 focus:ring-1 focus:ring-rose-500"></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
                            <button type="button" @click="rejectModalOpen = false"
                                    class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium text-xs rounded-xl transition-colors">
                                Batal
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs rounded-xl transition-all shadow-md shadow-rose-600/30">
                                Ya, Tolak Tiket Ini
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
