<x-admin.layouts.app>
    <div class="max-w-4xl space-y-8">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-white">Pengaturan Profil Admin</h1>
            <p class="mt-1 text-sm text-slate-400">Kelola identitas, kredensial login, dan otentikasi dua langkah (2FA) Anda.</p>
        </div>

        {{-- Form Edit Data Diri & Password --}}
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8">
            <h2 class="text-lg font-semibold text-white mb-6">Informasi Akun & Password</h2>

            <form method="POST" action="{{ route('admin.profile.update') }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-300">Nama Lengkap</label>
                        <input id="name" name="name" type="text" required
                               value="{{ old('name', $admin->name) }}"
                               class="mt-1.5 block w-full rounded-xl bg-slate-950/70 border @error('name') border-rose-500 @else border-slate-700 @enderror px-3.5 py-2.5 text-white placeholder-slate-500 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm">
                        @error('name')
                            <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-300">Alamat Email</label>
                        <input id="email" name="email" type="email" required
                               value="{{ old('email', $admin->email) }}"
                               class="mt-1.5 block w-full rounded-xl bg-slate-950/70 border @error('email') border-rose-500 @else border-slate-700 @enderror px-3.5 py-2.5 text-white placeholder-slate-500 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm">
                        @error('email')
                            <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="pt-6 border-t border-slate-800">
                    <h3 class="text-sm font-semibold text-slate-200 mb-1">Ganti Password (Opsional)</h3>
                    <p class="text-xs text-slate-400 mb-4">Kosongkan kolom di bawah jika Anda tidak ingin mengganti password.</p>

                    <div class="space-y-4">
                        <div>
                            <label for="current_password" class="block text-sm font-medium text-slate-300">Password Saat Ini</label>
                            <input id="current_password" name="current_password" type="password" autocomplete="current-password"
                                   class="mt-1.5 block w-full sm:w-1/2 rounded-xl bg-slate-950/70 border @error('current_password') border-rose-500 @else border-slate-700 @enderror px-3.5 py-2.5 text-white placeholder-slate-500 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                   placeholder="••••••••">
                            @error('current_password')
                                <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="password" class="block text-sm font-medium text-slate-300">Password Baru</label>
                                <input id="password" name="password" type="password" autocomplete="new-password"
                                       class="mt-1.5 block w-full rounded-xl bg-slate-950/70 border @error('password') border-rose-500 @else border-slate-700 @enderror px-3.5 py-2.5 text-white placeholder-slate-500 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                       placeholder="Minimal 8 karakter">
                                @error('password')
                                    <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="password_confirmation" class="block text-sm font-medium text-slate-300">Ulangi Password Baru</label>
                                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                                       class="mt-1.5 block w-full rounded-xl bg-slate-950/70 border border-slate-700 px-3.5 py-2.5 text-white placeholder-slate-500 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                       placeholder="Ulangi password baru">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-4">
                    <button type="submit"
                            class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/20 hover:bg-indigo-500 transition-colors">
                        Simpan Perubahan Profil
                    </button>
                </div>
            </form>
        </div>

        {{-- Kartu Keamanan 2FA --}}
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                        <span>Two-Factor Authentication (2FA)</span>
                        @if($admin->hasTwoFactor())
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                Aktif
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                Belum Aktif
                            </span>
                        @endif
                    </h2>
                    <p class="mt-1 text-sm text-slate-400">
                        Lindungi akun Anda dengan lapisan verifikasi tambahan dari Google Authenticator saat login.
                    </p>
                </div>

                <div>
                    @if($admin->hasTwoFactor())
                        <form method="POST" action="{{ route('admin.profile.2fa.disable') }}" onsubmit="return confirm('Apakah Anda yakin ingin menonaktifkan 2FA?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="rounded-xl bg-rose-500/10 border border-rose-500/20 px-4 py-2 text-sm font-semibold text-rose-400 hover:bg-rose-500/20 transition-colors">
                                Nonaktifkan 2FA
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.profile.2fa.enable') }}">
                            @csrf
                            <button type="submit"
                                    class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-500/20 hover:bg-indigo-500 transition-colors">
                                Aktifkan 2FA Sekarang
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-admin.layouts.app>
