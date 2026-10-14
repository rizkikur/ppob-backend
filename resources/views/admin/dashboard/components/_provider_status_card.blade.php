<div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-sm flex flex-col h-full">
    <div class="flex items-center justify-between pb-4 border-b border-slate-800">
        <div>
            <h2 class="text-base font-semibold text-white">Status Provider dan Circuit Breaker</h2>
            <p class="text-xs text-slate-400">Pemantauan kesehatan gateway upstream</p>
        </div>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-slate-800 text-slate-300">
            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
            Live Health
        </span>
    </div>

    <div class="mt-4 flex-1 space-y-3 overflow-y-auto max-h-80 pr-1">
        <template x-for="provider in providers" :key="provider.id">
            <div class="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800/80 flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-white" x-text="provider.name"></span>
                        <span class="font-mono text-xs px-1.5 py-0.5 rounded bg-slate-800 text-slate-400" x-text="provider.code"></span>
                    </div>
                    <div class="mt-1 text-xs text-slate-400">
                        Circuit: <span class="font-mono font-medium" x-text="provider.circuit_breaker_state"></span>
                    </div>
                </div>

                <div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold"
                          :class="{
                              'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20': provider.status === 'NORMAL',
                              'bg-amber-500/10 text-amber-400 border border-amber-500/20': provider.status === 'DEGRADED',
                              'bg-rose-500/10 text-rose-400 border border-rose-500/20 animate-pulse': provider.status === 'TRIPPED'
                          }"
                          x-text="provider.status">
                    </span>
                </div>
            </div>
        </template>

        {{-- Server Render Fallback if no JS --}}
        @if(empty($providers))
            <p class="text-xs text-slate-500 text-center py-6">Belum ada provider terdaftar di database.</p>
        @else
            <div x-show="providers.length === 0" class="space-y-3">
                @foreach($providers as $p)
                    <div class="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800/80 flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-white">{{ $p['name'] }}</span>
                                <span class="font-mono text-xs px-1.5 py-0.5 rounded bg-slate-800 text-slate-400">{{ $p['code'] }}</span>
                            </div>
                            <div class="mt-1 text-xs text-slate-400">
                                Circuit: <span class="font-mono font-medium">{{ $p['circuit_breaker_state'] }}</span>
                            </div>
                        </div>
                        <div>
                            @php
                                $badgeClass = match($p['status']) {
                                    'NORMAL' => 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20',
                                    'DEGRADED' => 'bg-amber-500/10 text-amber-400 border border-amber-500/20',
                                    default => 'bg-rose-500/10 text-rose-400 border border-rose-500/20',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $badgeClass }}">
                                {{ $p['status'] }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
