@extends('admin.layouts.app')

@section('title', 'Detail Log Audit #' . $auditLog->id)

@section('content')
<div class="space-y-6">
    {{-- Header & Breadcrumb --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.audit_logs.index') }}"
               class="p-2 rounded-xl bg-slate-800 text-slate-400 hover:text-white hover:bg-slate-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Detail Log Audit #{{ $auditLog->id }}</h1>
                <p class="text-sm text-slate-400">Dicatat pada {{ $auditLog->created_at->format('d M Y, H:i:s') }} ({{ $auditLog->created_at->diffForHumans() }})</p>
            </div>
        </div>

        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800/80 border border-slate-700/60 text-xs font-medium text-slate-300">
            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
            Append-Only Record
        </span>
    </div>

    {{-- Detail Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Metadata Column --}}
        <div class="space-y-6 lg:col-span-1">
            <div class="rounded-2xl bg-slate-900/60 border border-slate-800 p-5 space-y-4">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">Informasi Eksekutor</h2>

                <div>
                    <label class="block text-xs text-slate-500 mb-1">Administrator</label>
                    @if($auditLog->admin)
                        <div class="flex items-center gap-3 p-3 bg-slate-800/50 rounded-xl border border-slate-700/50">
                            <div class="w-10 h-10 rounded-full bg-indigo-600/20 text-indigo-400 flex items-center justify-center font-bold text-sm">
                                {{ strtoupper(substr($auditLog->admin->name, 0, 1)) }}
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-white">{{ $auditLog->admin->name }}</div>
                                <div class="text-xs text-slate-400">{{ $auditLog->admin->email }}</div>
                                <div class="text-xs font-mono text-indigo-400 mt-0.5">Role: {{ $auditLog->admin->role->value ?? $auditLog->admin->role }}</div>
                            </div>
                        </div>
                    @else
                        <div class="p-3 bg-slate-800/50 rounded-xl text-sm text-slate-400">Sistem / ID Admin #{{ $auditLog->admin_id }}</div>
                    @endif
                </div>

                <div class="border-t border-slate-800/80 pt-4 space-y-3">
                    <div>
                        <span class="block text-xs text-slate-500">Tindakan / Aksi</span>
                        <span class="inline-block mt-1 px-2.5 py-1 rounded-lg text-xs font-mono font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                            {{ $auditLog->action }}
                        </span>
                    </div>

                    <div>
                        <span class="block text-xs text-slate-500">Target Resource</span>
                        <div class="text-sm font-medium text-white mt-0.5">
                            {{ $auditLog->target_type ?? 'None' }}
                            @if($auditLog->target_id)
                                <span class="text-indigo-400 font-mono">#{{ $auditLog->target_id }}</span>
                            @endif
                        </div>
                    </div>

                    <div>
                        <span class="block text-xs text-slate-500">Alamat IP Admin (Client IP)</span>
                        <div class="text-sm font-mono text-slate-300 mt-0.5">{{ $auditLog->ip_address ?? '127.0.0.1' }}</div>
                        <span class="text-[10px] text-slate-500">IP komputer admin yang mengeksekusi aksi dari browser</span>
                    </div>

                    <div>
                        <span class="block text-xs text-slate-500">User Agent</span>
                        <div class="text-xs font-mono text-slate-400 bg-slate-950 p-2.5 rounded-lg border border-slate-800 mt-0.5 break-all">
                            {{ $auditLog->user_agent }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Reason Card --}}
            <div class="rounded-2xl bg-amber-500/5 border border-amber-500/20 p-5">
                <h3 class="text-xs font-bold uppercase tracking-wider text-amber-400 mb-2">Alasan / Catatan Tertulis</h3>
                <p class="text-sm text-amber-100 leading-relaxed">
                    {{ $auditLog->reason ?? 'Tidak ada alasan khusus yang dicantumkan saat melakukan tindakan ini.' }}
                </p>
            </div>
        </div>

        {{-- Changes Payload Diff Column --}}
        <div class="lg:col-span-2">
            <div class="rounded-2xl bg-slate-900/60 border border-slate-800 p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-white">Payload Perubahan (`changes`)</h2>
                        <p class="text-xs text-slate-400">Snapshot state data sebelum/sesudah atau parameter aksi</p>
                    </div>

                    <button type="button"
                            onclick="navigator.clipboard.writeText(document.getElementById('json-content').innerText); alert('Payload berhasil disalin ke clipboard!');"
                            class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-medium text-indigo-400 hover:text-indigo-300 transition-colors border border-slate-700">
                        Salin JSON
                    </button>
                </div>

                <div class="relative">
                    <pre id="json-content"
                         class="bg-slate-950 p-5 rounded-xl border border-slate-800 text-xs font-mono text-emerald-400 overflow-x-auto max-h-[600px] leading-relaxed">{{ json_encode($auditLog->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
