<div class="bg-slate-800/80 backdrop-blur-md rounded-2xl border border-slate-700/60 p-5 shadow-xl space-y-4">
    <div class="flex items-center justify-between border-b border-slate-700/60 pb-3">
        <h2 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
            <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>Timeline Lifecycle & Audit Trail</span>
        </h2>
        <span class="text-xs text-slate-400 font-mono">{{ $auditLogs->count() }} Intervensi</span>
    </div>

    <div class="relative pl-6 space-y-6 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-700">
        {{-- Event: Transaction Created --}}
        <div class="relative group">
            <span class="absolute -left-6 top-1 w-4 h-4 rounded-full bg-slate-900 border-2 border-indigo-500"></span>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-200">Transaksi Dibuat</span>
                    <span class="text-[11px] text-slate-400 font-mono">{{ $transaction->created_at?->format('d M Y H:i:s') }}</span>
                </div>
                <p class="text-xs text-slate-400 mt-0.5">
                    Order diinisiasi oleh user <span class="text-slate-300 font-medium">{{ $transaction->user?->name ?? 'User #'.$transaction->user_id }}</span>.
                </p>
                <div class="text-[11px] font-mono text-slate-500 mt-0.5">
                    Idempotency: {{ $transaction->idempotency_key }}
                </div>
            </div>
        </div>

        {{-- Event: Status Updates & Admin Audits --}}
        @foreach($auditLogs as $log)
            <div class="relative group">
                @php
                    $isRefund = str_contains($log->action, 'refund');
                    $isRequery = str_contains($log->action, 'requery');
                    $dotColor = $isRefund ? 'border-purple-500' : ($isRequery ? 'border-blue-500' : 'border-amber-500');
                @endphp
                <span class="absolute -left-6 top-1 w-4 h-4 rounded-full bg-slate-900 border-2 {{ $dotColor }}"></span>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-slate-200">
                            @if($isRefund)
                                Manual Refund oleh Admin
                            @elseif($isRequery)
                                Requery Provider oleh Admin
                            @else
                                Status Override oleh Admin
                            @endif
                        </span>
                        <span class="text-[11px] text-slate-400 font-mono">{{ $log->created_at?->format('d M Y H:i:s') }}</span>
                    </div>
                    <div class="text-xs text-slate-300 mt-0.5">
                        Pelaksana: <span class="font-medium text-indigo-400">{{ $log->admin?->name ?? 'Admin #'.$log->admin_id }}</span>
                        ({{ $log->admin?->role?->label() ?? 'Admin' }})
                    </div>
                    @if($log->reason)
                        <div class="text-xs text-slate-400 mt-1 p-2 rounded-lg bg-slate-900/60 border border-slate-700/40">
                            <span class="text-slate-500 font-medium">Alasan/Catatan:</span> {{ $log->reason }}
                        </div>
                    @endif
                    @if(!empty($log->changes))
                        <div class="text-[11px] font-mono text-slate-500 mt-1">
                            Perubahan: {{ json_encode($log->changes) }}
                        </div>
                    @endif
                </div>
            </div>
        @endforeach

        {{-- Final / Current Status --}}
        <div class="relative group">
            @php
                $statusColor = match($transaction->status) {
                    'success' => 'border-emerald-500 bg-emerald-500/20',
                    'refunded' => 'border-purple-500 bg-purple-500/20',
                    'failed' => 'border-rose-500 bg-rose-500/20',
                    default => 'border-amber-500 bg-amber-500/20',
                };
            @endphp
            <span class="absolute -left-6 top-1 w-4 h-4 rounded-full border-2 {{ $statusColor }}"></span>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-200">Status Saat Ini: {{ strtoupper($transaction->status) }}</span>
                    <span class="text-[11px] text-slate-400 font-mono">{{ $transaction->updated_at?->format('d M Y H:i:s') }}</span>
                </div>
                @if($transaction->failure_reason)
                    <p class="text-xs text-rose-400 mt-0.5">
                        Alasan Gagal / Catatan: {{ $transaction->failure_reason }}
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>
