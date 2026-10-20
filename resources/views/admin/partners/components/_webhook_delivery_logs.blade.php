{{-- Webhook Delivery Logs & Retry Center Component --}}
<div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-6">
    <div class="flex items-center justify-between mb-4 pb-4 border-b border-slate-800">
        <div>
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <span>Webhook Delivery & Retry Center</span>
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">Riwayat pengiriman notifikasi transaksi ke callback URL mitra beserta mekanisme retry</p>
        </div>

        <div class="flex items-center gap-3">
            <span class="text-xs text-slate-400 hidden sm:inline">Menampilkan 30 riwayat terakhir</span>
            <form action="{{ route('admin.partners.webhooks.ping', $partner) }}" method="POST" class="inline">
                @csrf
                <button type="submit"
                        {{ empty($partner->callback_url) ? 'disabled title=Callback-URL-belum-diisi' : 'title=Kirim-event-ping.test-ke-callback-URL' }}
                        class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 disabled:cursor-not-allowed text-white font-semibold text-xs rounded-xl transition-all shadow-md shadow-indigo-600/30 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span>Test Ping Webhook</span>
                </button>
            </form>
        </div>
    </div>

    @if($partner->webhookDeliveries->isEmpty())
        <div class="p-8 text-center bg-slate-950/40 rounded-xl border border-slate-800/80">
            <svg class="w-10 h-10 text-slate-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 4H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-2m-4-1v8m0 0l3-3m-3 3L9 8m-5 5h2.586a1 1 0 01.707.293l2.414 2.414a1 1 0 00.707.293h3.172a1 1 0 00.707-.293l2.414-2.414a1 1 0 01.707-.293H20"/></svg>
            <p class="text-xs text-slate-400 font-medium">Belum ada pengiriman callback webhook untuk mitra ini.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 uppercase tracking-wider font-semibold">
                        <th class="py-2.5 px-3">ID & Waktu</th>
                        <th class="py-2.5 px-3">Trx ID</th>
                        <th class="py-2.5 px-3">Status</th>
                        <th class="py-2.5 px-3">Percobaan</th>
                        <th class="py-2.5 px-3">HTTP Code</th>
                        <th class="py-2.5 px-3">Respon Server</th>
                        <th class="py-2.5 px-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-slate-300">
                    @foreach($partner->webhookDeliveries as $delivery)
                        @php
                            $statusBadge = match($delivery->status) {
                                'delivered' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                'failed_permanent' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                                default => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                            };
                            $statusLabel = match($delivery->status) {
                                'delivered' => 'Delivered (200 OK)',
                                'failed_permanent' => 'Gagal Permanen',
                                'failed' => 'Gagal (Antre Retry)',
                                default => 'Pending',
                            };
                        @endphp
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="py-2.5 px-3 whitespace-nowrap">
                                <div class="font-mono text-white font-medium">#{{ $delivery->id }}</div>
                                <div class="text-[10px] text-slate-500">{{ $delivery->created_at->format('d M H:i:s') }}</div>
                            </td>
                            <td class="py-2.5 px-3 whitespace-nowrap font-mono text-indigo-300">
                                @if($delivery->transaction_id && $delivery->transaction_id > 0)
                                    <a href="{{ route('admin.transactions.show', $delivery->transaction_id) }}" class="hover:underline">
                                        #{{ $delivery->transaction_id }}
                                    </a>
                                @else
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                                        PING TEST
                                    </span>
                                @endif
                            </td>
                            <td class="py-2.5 px-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border {{ $statusBadge }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 whitespace-nowrap">
                                <span class="font-mono text-slate-400">Attempt {{ $delivery->attempt }} / {{ \App\Domain\Partner\Models\WebhookDelivery::MAX_ATTEMPTS }}</span>
                            </td>
                            <td class="py-2.5 px-3 whitespace-nowrap font-mono">
                                <span class="{{ $delivery->response_code >= 200 && $delivery->response_code < 300 ? 'text-emerald-400' : 'text-rose-400' }}">
                                    {{ $delivery->response_code ?: 'Timeout / 0' }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 max-w-xs truncate font-mono text-[11px] text-slate-400" title="{{ $delivery->response_body }}">
                                {{ $delivery->response_body ?: '-' }}
                            </td>
                            <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                @if(!$delivery->isDelivered())
                                    <form action="{{ route('admin.partners.webhooks.retry', $delivery) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit"
                                                class="px-2.5 py-1 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/30 rounded-lg text-[11px] font-medium transition-all flex items-center gap-1 inline-flex"
                                                title="Replay / Kirim ulang webhook sekarang">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                            <span>Retry</span>
                                        </button>
                                    </form>
                                @else
                                    <span class="text-[11px] text-emerald-400 font-medium">Terkirim</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
