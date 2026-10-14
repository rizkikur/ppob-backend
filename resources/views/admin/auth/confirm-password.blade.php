<x-admin.layouts.guest>
    <div class="mb-6 text-center">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 mb-3">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>
        <h3 class="text-lg font-semibold text-white">Konfirmasi Password Diperlukan</h3>
        <p class="mt-1 text-sm text-slate-400">Ini adalah area atau aksi dengan risiko tinggi. Silakan konfirmasi password Anda untuk melanjutkan.</p>
    </div>

    <form method="POST" action="{{ route('admin.password.confirm.submit') }}" class="space-y-5">
        @csrf

        {{-- Password Field --}}
        <div>
            <label for="password" class="block text-sm font-medium text-slate-300">Password Akun Anda</label>
            <div class="mt-1.5">
                <input id="password" name="password" type="password" required autofocus
                       class="block w-full rounded-xl bg-slate-950/70 border @error('password') border-rose-500 @else border-slate-700 @enderror px-3.5 py-2.5 text-white placeholder-slate-500 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                       placeholder="••••••••">
            </div>
            @error('password')
                <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Submit Button --}}
        <div>
            <button type="submit"
                    class="flex w-full justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition-colors">
                Konfirmasi Password
            </button>
        </div>
    </form>
</x-admin.layouts.guest>
