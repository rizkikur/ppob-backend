@extends('admin.layouts.app')

@section('title', 'Edit Profil Mitra ' . $partner->name)

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
                <svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                <span>Edit Konfigurasi Mitra: {{ $partner->name }}</span>
            </h1>
            <p class="text-xs text-slate-400 mt-1">Perbarui parameter protokol, batasan rate-limit, dan URL webhook</p>
        </div>

        <a href="{{ route('admin.partners.show', $partner) }}"
           class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-xs rounded-xl transition-colors flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>Kembali ke Detail Mitra</span>
        </a>
    </div>

    {{-- Form --}}
    <form action="{{ route('admin.partners.update', $partner) }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- 1. Profil Mitra --}}
        <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-6 space-y-4">
            <h3 class="text-sm font-bold text-white uppercase tracking-wider text-indigo-400 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <span>1. Profil Mitra</span>
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Nama Perusahaan / Mitra <span class="text-rose-400">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $partner->name) }}" required
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:border-indigo-500">
                    @error('name') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- 2. Spesifikasi Teknis API --}}
        <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-6 space-y-4">
            <h3 class="text-sm font-bold text-white uppercase tracking-wider text-indigo-400 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                <span>2. Konfigurasi Protokol H2H & Batasan</span>
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Protokol Request</label>
                    <select name="protocol" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="json" {{ old('protocol', $partner->protocol) === 'json' ? 'selected' : '' }}>JSON REST API</option>
                        <option value="otomax" {{ old('protocol', $partner->protocol) === 'otomax' ? 'selected' : '' }}>Otomax Engine</option>
                        <option value="irs" {{ old('protocol', $partner->protocol) === 'irs' ? 'selected' : '' }}>IRS Protocol</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Mode Respon</label>
                    <select name="response_mode" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="sync" {{ old('response_mode', $partner->response_mode) === 'sync' ? 'selected' : '' }}>Sync</option>
                        <option value="async" {{ old('response_mode', $partner->response_mode) === 'async' ? 'selected' : '' }}>Async (Callback Webhook)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Rate Limit (Req / Menit)</label>
                    <input type="number" name="rate_limit_rpm" value="{{ old('rate_limit_rpm', $partner->rate_limit_rpm) }}" min="10" max="1000" required
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Max Response Timeout (ms)</label>
                    <input type="number" name="response_timeout_ms" value="{{ old('response_timeout_ms', $partner->response_timeout_ms) }}" min="1000" max="30000" step="500" required
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>
            </div>
        </div>

        {{-- 3. Webhook Callback URL --}}
        <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-2xl p-6 space-y-4">
            <h3 class="text-sm font-bold text-white uppercase tracking-wider text-indigo-400 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <span>3. Webhook Callback</span>
            </h3>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Webhook Callback URL</label>
                <input type="url" name="callback_url" value="{{ old('callback_url', $partner->callback_url) }}" placeholder="https://api.mitra.com/webhooks/ppob-callback"
                       class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs font-mono text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                @error('callback_url') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-2 pt-2">
                <input type="checkbox" id="is_active" name="is_active" value="1" {{ $partner->is_active ? 'checked' : '' }}
                       class="rounded bg-slate-950 border-slate-800 text-indigo-600 focus:ring-indigo-500">
                <label for="is_active" class="text-xs text-slate-300">Status Akses API Aktif (Uncheck untuk suspend mitra)</label>
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.partners.show', $partner) }}"
               class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-xs rounded-xl transition-colors">
                Batal
            </a>
            <button type="submit"
                    class="px-6 py-2.5 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-500 hover:to-indigo-600 text-white font-bold text-xs rounded-xl transition-all shadow-lg shadow-indigo-600/30">
                Simpan Perubahan
            </button>
        </div>
    </form>
</div>
@endsection
