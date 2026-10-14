<x-admin.layouts.app>
    <div class="max-w-2xl space-y-6">
        <div>
            <a href="{{ route('admin.profile') }}" class="text-xs text-indigo-400 hover:underline flex items-center gap-1 mb-2">
                &larr; Kembali ke Profil
            </a>
            <h1 class="text-2xl font-bold tracking-tight text-white">Setup Two-Factor Authentication</h1>
            <p class="mt-1 text-sm text-slate-400">Ikuti 2 langkah mudah di bawah untuk menghubungkan Google Authenticator.</p>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 space-y-6">
            {{-- Langkah 1: Kunci Manual / URI --}}
            <div class="space-y-3">
                <h2 class="text-base font-semibold text-white flex items-center gap-2">
                    <span class="flex items-center justify-center w-6 h-6 rounded-full bg-indigo-600 text-white text-xs">1</span>
                    <span>Masukkan Kunci Manual ke Google Authenticator</span>
                </h2>
                <p class="text-sm text-slate-400">
                    Buka aplikasi Google Authenticator, pilih <strong>+ (Tambah Akun)</strong> &rarr; <strong>Masukkan kunci penyiapan</strong>:
                </p>

                <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 flex items-center justify-between">
                    <div>
                        <div class="text-xs text-slate-500 uppercase">Kunci Rahasia (Secret Key)</div>
                        <div class="mt-1 font-mono text-lg font-bold text-indigo-400 tracking-widest">{{ $secret }}</div>
                    </div>
                </div>

                <div class="text-xs text-slate-500">
                    Tipe akun: <code>Berbasis waktu (TOTP)</code> | Nama akun: <code>{{ $admin->email }}</code>
                </div>
            </div>

            {{-- Langkah 2: Konfirmasi Kode OTP --}}
            <div class="pt-6 border-t border-slate-800 space-y-3">
                <h2 class="text-base font-semibold text-white flex items-center gap-2">
                    <span class="flex items-center justify-center w-6 h-6 rounded-full bg-indigo-600 text-white text-xs">2</span>
                    <span>Konfirmasi Kode OTP 6-Digit</span>
                </h2>
                <p class="text-sm text-slate-400">
                    Masukkan kode 6-digit yang muncul di aplikasi Authenticator untuk memverifikasi pemasangan:
                </p>

                <form method="POST" action="{{ route('admin.profile.2fa.confirm') }}" class="space-y-4">
                    @csrf

                    <div>
                        <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" autofocus required
                               class="block w-full sm:w-64 text-center tracking-[0.5em] text-2xl font-mono rounded-xl bg-slate-950/70 border @error('code') border-rose-500 @else border-slate-700 @enderror px-3.5 py-2.5 text-white placeholder-slate-600 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                               placeholder="000000">
                        @error('code')
                            <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit"
                                class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/20 hover:bg-indigo-500 transition-colors">
                            Verifikasi & Aktifkan 2FA
                        </button>
                        <a href="{{ route('admin.profile') }}" class="text-sm text-slate-400 hover:text-slate-200">
                            Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin.layouts.app>
