<div class="bg-slate-800/80 backdrop-blur-md rounded-2xl border border-slate-700/60 p-5 shadow-xl space-y-4">
    <div class="flex items-center justify-between border-b border-slate-700/60 pb-3">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            <h2 class="text-sm font-bold text-white uppercase tracking-wider">Mutasi Buku Besar Dompet Terkait</h2>
        </div>
        <span class="text-xs text-slate-400 font-mono">{{ $walletMutations->count() }} Record</span>
    </div>

    @if($walletMutations->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-700/60 bg-slate-900/40 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                        <th class="py-2.5 px-3">ID Mutasi</th>
                        <th class="py-2.5 px-3">Waktu</th>
                        <th class="py-2.5 px-3">Tipe</th>
                        <th class="py-2.5 px-3 text-right">Nominal</th>
                        <th class="py-2.5 px-3 text-right">Saldo Sesudah</th>
                        <th class="py-2.5 px-3">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    @foreach($walletMutations as $mutation)
                        <tr class="hover:bg-slate-700/30">
                            <td class="py-2.5 px-3 font-mono font-medium text-slate-300">#{{ $mutation->id }}</td>
                            <td class="py-2.5 px-3 text-slate-400 whitespace-nowrap">{{ $mutation->created_at?->format('d M Y H:i:s') }}</td>
                            <td class="py-2.5 px-3 whitespace-nowrap">
                                @if($mutation->type === 'credit')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        + CREDIT (Masuk)
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                        - DEBIT (Keluar)
                                    </span>
                                @endif
                            </td>
                            <td class="py-2.5 px-3 text-right font-semibold whitespace-nowrap {{ $mutation->type === 'credit' ? 'text-emerald-400' : 'text-slate-200' }}">
                                {{ $mutation->amount_cents ? $mutation->amount_cents->format() : 'Rp 0,00' }}
                            </td>
                            <td class="py-2.5 px-3 text-right font-mono text-slate-300 whitespace-nowrap">
                                {{ $mutation->balance_after_cents ? $mutation->balance_after_cents->format() : '-' }}
                            </td>
                            <td class="py-2.5 px-3 text-slate-400 max-w-xs truncate" title="{{ $mutation->note }}">
                                {{ $mutation->note }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="py-6 text-center text-slate-500 text-xs">
            Tidak ditemukan mutasi saldo langsung untuk referensi transaksi ini.
        </div>
    @endif
</div>
