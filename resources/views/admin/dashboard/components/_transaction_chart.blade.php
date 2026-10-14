<div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-sm">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-4 border-b border-slate-800">
        <div>
            <h2 class="text-base font-semibold text-white">Tren Transaksi 24 Jam</h2>
            <p class="text-xs text-slate-400">Distribusi volume transaksi sukses vs gagal per jam</p>
        </div>
        <div class="flex items-center gap-4 text-xs">
            <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                <span class="text-slate-300">Sukses</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                <span class="text-slate-300">Gagal</span>
            </div>
        </div>
    </div>

    <div class="mt-4 relative h-72">
        <canvas id="hourlyTrendChart" class="w-full h-full"></canvas>
    </div>
</div>
