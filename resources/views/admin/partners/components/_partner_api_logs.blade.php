{{-- API Request Logs Component --}}
<div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-6">
    <div class="flex items-center justify-between mb-4 pb-4 border-b border-slate-800">
        <div>
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                <span>Log Pemanggilan API (Traffic Monitoring)</span>
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">Pemantauan aktivitas request masuk dari mitra untuk investigasi error dan performa</p>
        </div>

        <span class="text-xs text-slate-400">30 request terakhir</span>
    </div>

    @if($partner->logs->isEmpty())
        <div class="p-8 text-center bg-slate-950/40 rounded-xl border border-slate-800/80">
            <p class="text-xs text-slate-400 font-medium">Belum ada catatan traffic API dari mitra ini.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 uppercase tracking-wider font-semibold">
                        <th class="py-2.5 px-3">Waktu</th>
                        <th class="py-2.5 px-3">Metode & Endpoint</th>
                        <th class="py-2.5 px-3">IP Pemanggil</th>
                        <th class="py-2.5 px-3">Status</th>
                        <th class="py-2.5 px-3 text-right">Latensi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-slate-300">
                    @foreach($partner->logs as $log)
                        @php
                            $codeColor = match(true) {
                                $log->response_code >= 200 && $log->response_code < 300 => 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20',
                                $log->response_code >= 400 && $log->response_code < 500 => 'text-amber-400 bg-amber-500/10 border-amber-500/20',
                                default => 'text-rose-400 bg-rose-500/10 border-rose-500/20',
                            };
                        @endphp
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="py-2.5 px-3 whitespace-nowrap text-slate-400 font-mono text-[11px]">
                                {{ $log->created_at->format('d M H:i:s') }}
                            </td>
                            <td class="py-2.5 px-3 font-mono">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-slate-200 mr-1.5 uppercase">{{ $log->method }}</span>
                                <span class="text-white">{{ $log->endpoint }}</span>
                            </td>
                            <td class="py-2.5 px-3 font-mono text-[11px] text-slate-400">
                                {{ $log->ip_address }}
                            </td>
                            <td class="py-2.5 px-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono font-bold border {{ $codeColor }}">
                                    {{ $log->response_code }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 text-right font-mono text-slate-300 font-medium">
                                {{ $log->duration_ms }} ms
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
