<x-admin.layouts.guest>
    <div class="mb-6 text-center">
        <h3 class="text-lg font-semibold text-white">Verifikasi Dua Langkah (2FA)</h3>
        <p class="mt-1 text-sm text-slate-400">Buka aplikasi Google Authenticator Anda dan masukkan 6 digit kode keamanan.</p>
    </div>

    <form method="POST" action="{{ route('admin.two-factor.verify') }}" class="space-y-5">
        @csrf

        {{-- 2FA Code Field --}}
        <div>
            <label for="code" class="block text-center text-sm font-medium text-slate-300">Kode Autentikasi 6-Digit</label>
            <div class="mt-2">
                <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" autofocus required
                       class="block w-full text-center tracking-[0.5em] text-2xl font-mono rounded-xl bg-slate-950/70 border @error('code') border-rose-500 @else border-slate-700 @enderror px-3.5 py-3 text-white placeholder-slate-600 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                       placeholder="000000">
            </div>
            @error('code')
                <p class="mt-2 text-center text-xs text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Submit Button --}}
        <div>
            <button type="submit"
                    class="flex w-full justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition-colors">
                Verifikasi & Masuk
            </button>
        </div>
    </form>

    <div class="mt-6 border-t border-slate-800 pt-4 text-center">
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="text-xs text-slate-400 hover:text-slate-200">
                Batal dan Logout
            </button>
        </form>
    </div>
</x-admin.layouts.guest>
