{{-- RECEIPT & WHATSAPP MODAL --}}
<div x-show="showReceiptModal" x-cloak
     class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div @click.away="showReceiptModal = false"
         class="bg-slate-900 border border-slate-800 rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-5">
        
        {{-- Modal Header --}}
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Bukti Pembayaran Pelanggan</h3>
                    <p class="text-xs text-slate-400">Struk thermal 58mm/80mm & format teks siap kirim WhatsApp.</p>
                </div>
            </div>
            <button type="button" @click="showReceiptModal = false" class="text-slate-400 hover:text-white p-1">✕</button>
        </div>

        {{-- Tab Selector --}}
        <div class="flex items-center gap-2 p-1 bg-slate-950 rounded-xl border border-slate-800">
            <button type="button"
                    @click="receiptTab = 'thermal'"
                    :class="receiptTab === 'thermal' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-slate-200'"
                    class="flex-1 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Struk Thermal (Print)</span>
            </button>
            <button type="button"
                    @click="receiptTab = 'whatsapp'"
                    :class="receiptTab === 'whatsapp' ? 'bg-emerald-600 text-white shadow-md' : 'text-slate-400 hover:text-slate-200'"
                    class="flex-1 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                <span>Format Teks WhatsApp</span>
            </button>
        </div>

        {{-- TAB CONTENT 1: THERMAL RECEIPT --}}
        <div x-show="receiptTab === 'thermal'" class="space-y-4">
            <div class="flex justify-center p-3 bg-slate-950/60 rounded-2xl border border-slate-800 max-h-[380px] overflow-y-auto">
                {{-- Thermal Paper Simulation Container --}}
                <div id="thermal-receipt-print-area" class="w-full max-w-[310px] bg-white text-black p-5 rounded shadow-lg font-mono text-[11px] leading-tight space-y-3 print:p-0 print:shadow-none print:max-w-none">
                    <div class="text-center space-y-1">
                        <div class="text-sm font-extrabold uppercase tracking-wide">STRUK PEMBAYARAN RESMI</div>
                        <div class="text-[10px] text-gray-600">LOKET PEMBAYARAN DIGITAL PPOB</div>
                        <div class="text-[9px] text-gray-500">Tanggal: {{ $transaction->created_at?->format('d/m/Y H:i:s') }}</div>
                    </div>

                    <div class="border-t border-dashed border-gray-400 pt-2 space-y-1">
                        <div class="flex justify-between">
                            <span class="text-gray-600">No. Ref:</span>
                            <span class="font-bold">#{{ $transaction->id }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">No. Tujuan:</span>
                            <span class="font-bold select-all">{{ $transaction->customer_number }}</span>
                        </div>
                        @if($transaction->inquiry && !empty($transaction->inquiry->provider_response['customer_name']))
                            <div class="flex justify-between">
                                <span class="text-gray-600">Nama Pelanggan:</span>
                                <span class="font-bold uppercase truncate max-w-[160px]">{{ $transaction->inquiry->provider_response['customer_name'] }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between">
                            <span class="text-gray-600">Produk/Layanan:</span>
                            <span class="font-bold text-right truncate max-w-[160px]">{{ $transaction->product?->name ?? 'Layanan PPOB' }}</span>
                        </div>
                    </div>

                    @if($sn)
                        <div class="border-t border-b border-dashed border-gray-400 py-2.5 text-center space-y-1">
                            <div class="text-[10px] font-bold text-gray-700 uppercase">SN / TOKEN LISTRIK</div>
                            <div class="text-base font-extrabold tracking-widest text-black select-all py-0.5">{{ $sn }}</div>
                        </div>
                    @endif

                    <div class="border-t border-dashed border-gray-400 pt-2 space-y-1">
                        <div class="flex justify-between text-xs font-bold">
                            <span>TOTAL BAYAR:</span>
                            <span>{{ $transaction->sell_price_cents ? $transaction->sell_price_cents->format() : 'Rp 0' }}</span>
                        </div>
                        <div class="flex justify-between text-[10px]">
                            <span class="text-gray-600">STATUS:</span>
                            <span class="font-bold uppercase text-black">{{ $transaction->status === 'success' ? 'LUNAS / BERHASIL' : strtoupper($transaction->status) }}</span>
                        </div>
                    </div>

                    <div class="border-t border-dashed border-gray-400 pt-2 text-center text-[9px] text-gray-500 space-y-0.5">
                        <p>Simpan struk ini sebagai bukti pembayaran sah.</p>
                        <p>Terima kasih atas kepercayaan Anda.</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" @click="showReceiptModal = false" class="px-4 py-2 rounded-xl text-xs font-medium text-slate-400 hover:text-white bg-slate-800">
                    Tutup
                </button>
                <button type="button"
                        onclick="window.print()"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Cetak Struk (Thermal / PDF)</span>
                </button>
            </div>
        </div>

        {{-- TAB CONTENT 2: WHATSAPP TEXT FORMAT --}}
        <div x-show="receiptTab === 'whatsapp'" class="space-y-4">
            @php
                $waText = "*BUKTI TRANSAKSI PEMBAYARAN RESMI*\n"
                    . "═════════════════════════\n"
                    . "📅 *Waktu:* " . ($transaction->created_at?->format('d/m/Y H:i:s') ?? '-') . "\n"
                    . "🔢 *No. Ref:* #" . $transaction->id . "\n"
                    . "📱 *No. Tujuan:* " . $transaction->customer_number . "\n";
                if ($transaction->inquiry && !empty($transaction->inquiry->provider_response['customer_name'])) {
                    $waText .= "👤 *Nama Pelanggan:* " . $transaction->inquiry->provider_response['customer_name'] . "\n";
                }
                $waText .= "📦 *Layanan:* " . ($transaction->product?->name ?? 'Layanan PPOB') . "\n";
                if ($sn) {
                    $waText .= "🔑 *SN / Token PLN:*\n`" . $sn . "`\n";
                }
                $waText .= "💵 *Total Tagihan:* " . ($transaction->sell_price_cents ? $transaction->sell_price_cents->format() : 'Rp 0') . "\n"
                    . "📌 *Status:* " . ($transaction->status === 'success' ? '✅ BERHASIL / LUNAS' : '⚠️ ' . strtoupper($transaction->status)) . "\n"
                    . "═════════════════════════\n"
                    . "_Simpan pesan ini sebagai bukti pembayaran resmi yang sah. Terima kasih._";
            @endphp

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                    Template Teks WhatsApp (Siap Kirim ke Pelanggan)
                </label>
                <textarea id="wa-text-template"
                          rows="9"
                          readonly
                          class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3.5 font-mono text-xs text-slate-200 select-all focus:outline-none focus:ring-1 focus:ring-emerald-500 leading-relaxed">{{ $waText }}</textarea>
            </div>

            <div class="flex items-center justify-between pt-2">
                <span class="text-xs text-slate-400">Tekan tombol salin untuk langsung kirim di chat WhatsApp CS.</span>
                <div class="flex items-center gap-2">
                    <button type="button" @click="showReceiptModal = false" class="px-4 py-2 rounded-xl text-xs font-medium text-slate-400 hover:text-white bg-slate-800">
                        Tutup
                    </button>
                    <button type="button"
                            @click="navigator.clipboard.writeText(document.getElementById('wa-text-template').value); copiedWa = true; setTimeout(() => copiedWa = false, 2500)"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold shadow-lg shadow-emerald-600/30 transition-all font-sans">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                        <span x-text="copiedWa ? 'Berhasil Disalin!' : 'Salin Teks WhatsApp'">Salin Teks WhatsApp</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
