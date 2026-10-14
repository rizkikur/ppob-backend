@extends('admin.layouts.app')

@section('title', 'Audit Trail & Log Aktivitas Admin')

@section('content')
<div class="space-y-6" x-data="auditLogViewer()">
    {{-- Header Section --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight">Audit Trail & Log Aktivitas</h1>
                    <p class="text-sm text-slate-400">Pencatatan append-only seluruh tindakan administratif sistem dan intervensi operasional</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800/80 border border-slate-700/60 text-xs font-medium text-slate-300">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                Immutable Append-Only
            </span>
        </div>
    </div>

    {{-- Filter Panel --}}
    <div class="rounded-2xl bg-slate-900/60 border border-slate-800 p-5 backdrop-blur-sm">
        <form method="GET" action="{{ route('admin.audit_logs.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
            {{-- Search Keyword --}}
            <div class="lg:col-span-2">
                <label class="block text-xs font-medium text-slate-400 mb-1">Cari Keyword</label>
                <div class="relative">
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Alasan, ID target, IP..."
                           class="w-full bg-slate-800/80 border border-slate-700 rounded-xl px-3 py-2 pl-9 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition-colors">
                    <svg class="w-4 h-4 text-slate-500 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>

            {{-- Admin Filter --}}
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1">Administrator</label>
                <select name="admin_id" class="w-full bg-slate-800/80 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                    <option value="">Semua Admin</option>
                    @foreach($admins as $adm)
                        <option value="{{ $adm->id }}" {{ request('admin_id') == $adm->id ? 'selected' : '' }}>
                            {{ $adm->name }} ({{ $adm->role->value ?? $adm->role }})
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Action Filter --}}
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1">Aksi</label>
                <select name="action" class="w-full bg-slate-800/80 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                    <option value="">Semua Aksi</option>
                    @foreach($distinctActions as $act)
                        <option value="{{ $act }}" {{ request('action') == $act ? 'selected' : '' }}>
                            {{ $act }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Target Type Filter --}}
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1">Tipe Target</label>
                <select name="target_type" class="w-full bg-slate-800/80 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                    <option value="">Semua Target</option>
                    @foreach($distinctTargets as $tgt)
                        <option value="{{ $tgt }}" {{ request('target_type') == $tgt ? 'selected' : '' }}>
                            {{ $tgt }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Date Range (From/To) & Action Buttons --}}
            <div class="flex items-end gap-2 lg:col-span-1">
                <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-sm py-2 px-3 rounded-xl transition-colors flex items-center justify-center gap-1.5 shadow-sm shadow-indigo-600/30">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    <span>Filter</span>
                </button>
                @if(request()->hasAny(['search', 'admin_id', 'action', 'target_type', 'date_from', 'date_to']))
                    <a href="{{ route('admin.audit_logs.index') }}" title="Reset Filter" class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </a>
                @endif
            </div>

            {{-- Date Filters Row --}}
            <div class="lg:col-span-3">
                <label class="block text-xs font-medium text-slate-400 mb-1">Rentang Tanggal (Dari)</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       class="w-full bg-slate-800/80 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
            </div>
            <div class="lg:col-span-3">
                <label class="block text-xs font-medium text-slate-400 mb-1">Rentang Tanggal (Sampai)</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       class="w-full bg-slate-800/80 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
            </div>
        </form>
    </div>

    {{-- Audit Logs Table Card --}}
    <div class="rounded-2xl bg-slate-900/60 border border-slate-800 overflow-hidden shadow-xl backdrop-blur-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-800 bg-slate-900/80 text-xs font-semibold text-slate-400 uppercase tracking-wider">
                        <th class="py-3.5 px-4">Waktu</th>
                        <th class="py-3.5 px-4">Administrator</th>
                        <th class="py-3.5 px-4">Aksi</th>
                        <th class="py-3.5 px-4">Target</th>
                        <th class="py-3.5 px-4">Alasan / Catatan</th>
                        <th class="py-3.5 px-4">IP & Browser</th>
                        <th class="py-3.5 px-4 text-right">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-sm">
                    @forelse($logs as $log)
                        @php
                            // Semantic color for actions
                            $actionColor = match(true) {
                                str_contains($log->action, 'refund') || str_contains($log->action, 'reject') || str_contains($log->action, 'freeze') => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                                str_contains($log->action, 'approve') || str_contains($log->action, 'credit') => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                str_contains($log->action, 'override') || str_contains($log->action, 'reset') || str_contains($log->action, 'toggle') => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                str_contains($log->action, 'key') || str_contains($log->action, 'secret') || str_contains($log->action, 'create') => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20',
                                default => 'bg-slate-700/30 text-slate-300 border-slate-600/30',
                            };
                        @endphp
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            {{-- Timestamp --}}
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <div class="font-medium text-slate-200">{{ $log->created_at->format('d M Y') }}</div>
                                <div class="text-xs text-slate-400">{{ $log->created_at->format('H:i:s') }} <span class="text-slate-600">({{ $log->created_at->diffForHumans() }})</span></div>
                            </td>

                            {{-- Admin --}}
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                @if($log->admin)
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-xs text-indigo-400">
                                            {{ strtoupper(substr($log->admin->name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="font-medium text-white">{{ $log->admin->name }}</div>
                                            <div class="text-xs text-slate-400">{{ $log->admin->email }}</div>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-slate-500 italic">Sistem / ID #{{ $log->admin_id }}</span>
                                @endif
                            </td>

                            {{-- Action Badge --}}
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium border font-mono {{ $actionColor }}">
                                    {{ $log->action }}
                                </span>
                            </td>

                            {{-- Target --}}
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                @if($log->target_type)
                                    <div class="flex items-center gap-1.5">
                                        <span class="px-2 py-0.5 rounded bg-slate-800 text-xs text-slate-300 border border-slate-700/60 font-medium">
                                            {{ $log->target_type }}
                                        </span>
                                        @if($log->target_id)
                                            <span class="text-xs font-mono text-indigo-400">#{{ $log->target_id }}</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-slate-500 text-xs">—</span>
                                @endif
                            </td>

                            {{-- Reason --}}
                            <td class="py-3.5 px-4 max-w-xs">
                                @if($log->reason)
                                    <div class="text-xs text-slate-300 truncate" title="{{ $log->reason }}">
                                        {{ $log->reason }}
                                    </div>
                                @else
                                    <span class="text-slate-500 text-xs italic">Tanpa catatan</span>
                                @endif
                            </td>

                            {{-- IP & Agent --}}
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <div class="text-xs font-mono text-slate-300">{{ $log->ip_address ?? '127.0.0.1' }}</div>
                                <div class="text-xs text-slate-500 truncate max-w-[140px]" title="{{ $log->user_agent }}">
                                    {{ $log->user_agent }}
                                </div>
                            </td>

                            {{-- Action Button --}}
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <button type="button"
                                        @click="showDetail({{ json_encode($log) }})"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-medium text-slate-300 hover:text-white transition-colors border border-slate-700/60">
                                    <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <span>Diff</span>
                                </button>
                                <a href="{{ route('admin.audit_logs.show', $log) }}"
                                   title="Halaman Penuh"
                                   class="p-1.5 rounded-xl text-slate-500 hover:text-white transition-colors">
                                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400">
                                <svg class="w-12 h-12 mx-auto text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <p class="text-base font-semibold text-slate-300">Tidak ada log audit yang sesuai filter</p>
                                <p class="text-xs text-slate-500 mt-1">Gunakan tombol reset filter untuk melihat seluruh rekaman log.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($logs->hasPages())
            <div class="p-4 border-t border-slate-800 bg-slate-900/40">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

    {{-- Detail Diff Modal (Alpine.js) --}}
    <div x-show="modalOpen"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm transition-opacity"
             @click="modalOpen = false"></div>

        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-3xl rounded-2xl bg-slate-900 border border-slate-800 shadow-2xl p-6 overflow-hidden">
                {{-- Modal Header --}}
                <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-0.5 rounded-lg text-xs font-mono font-medium bg-indigo-500/10 text-indigo-400 border border-indigo-500/20"
                                  x-text="currentLog?.action"></span>
                            <span class="text-xs text-slate-400" x-text="formatDate(currentLog?.created_at)"></span>
                        </div>
                        <h3 class="text-lg font-bold text-white mt-1">Detail Perubahan & Payload (Diff)</h3>
                    </div>
                    <button type="button" @click="modalOpen = false" class="p-2 text-slate-400 hover:text-white rounded-xl hover:bg-slate-800">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="mt-4 space-y-4">
                    {{-- Metadata Grid --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-800/40 p-3.5 rounded-xl border border-slate-800">
                        <div>
                            <div class="text-xs text-slate-500 uppercase">Administrator</div>
                            <div class="text-sm font-medium text-white truncate" x-text="currentLog?.admin?.name ?? 'Sistem'"></div>
                        </div>
                        <div>
                            <div class="text-xs text-slate-500 uppercase">Target</div>
                            <div class="text-sm font-medium text-indigo-400">
                                <span x-text="currentLog?.target_type ?? '—'"></span>
                                <span x-show="currentLog?.target_id" x-text="' #' + currentLog?.target_id"></span>
                            </div>
                        </div>
                        <div>
                            <div class="text-xs text-slate-500 uppercase">IP Address</div>
                            <div class="text-sm font-mono text-slate-300" x-text="currentLog?.ip_address ?? '—'"></div>
                        </div>
                        <div>
                            <div class="text-xs text-slate-500 uppercase">Waktu</div>
                            <div class="text-sm text-slate-300" x-text="formatDate(currentLog?.created_at)"></div>
                        </div>
                    </div>

                    {{-- Reason / Alasan --}}
                    <div class="bg-amber-500/5 border border-amber-500/20 rounded-xl p-3.5">
                        <div class="text-xs font-semibold text-amber-400 uppercase tracking-wider mb-1">Alasan / Catatan Admin</div>
                        <div class="text-sm text-amber-200" x-text="currentLog?.reason ?? 'Tidak ada catatan alasan yang dilampirkan.'"></div>
                    </div>

                    {{-- Payload Changes JSON Viewer --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Payload Changes (`changes` JSON)</span>
                            <button type="button" @click="copyPayload()" class="text-xs text-indigo-400 hover:text-indigo-300 font-medium">
                                <span x-text="copied ? 'Tersalin!' : 'Salin JSON'"></span>
                            </button>
                        </div>
                        <pre class="bg-slate-950 p-4 rounded-xl border border-slate-800 text-xs font-mono text-slate-200 overflow-x-auto max-h-80"
                             x-text="JSON.stringify(currentLog?.changes ?? {}, null, 2)"></pre>
                    </div>

                    {{-- User Agent --}}
                    <div>
                        <div class="text-xs text-slate-500 uppercase mb-1">User Agent</div>
                        <div class="text-xs font-mono text-slate-400 bg-slate-950/60 p-2.5 rounded-lg border border-slate-800 break-all"
                             x-text="currentLog?.user_agent ?? '—'"></div>
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="mt-6 flex justify-end">
                    <button type="button" @click="modalOpen = false"
                            class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-sm font-medium text-white transition-colors">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function auditLogViewer() {
        return {
            modalOpen: false,
            currentLog: null,
            copied: false,
            showDetail(log) {
                this.currentLog = log;
                this.modalOpen = true;
                this.copied = false;
            },
            copyPayload() {
                const text = JSON.stringify(this.currentLog?.changes ?? {}, null, 2);
                navigator.clipboard.writeText(text);
                this.copied = true;
                setTimeout(() => this.copied = false, 2000);
            },
            formatDate(dateStr) {
                if (!dateStr) return '—';
                const d = new Date(dateStr);
                return d.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'medium' });
            }
        }
    }
</script>
@endsection
