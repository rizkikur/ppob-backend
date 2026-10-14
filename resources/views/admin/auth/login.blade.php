<x-admin.layouts.guest>
    <form method="POST" action="{{ route('admin.login.submit') }}" class="space-y-5">
        @csrf

        {{-- Email Field --}}
        <div>
            <label for="email" class="block text-sm font-medium text-slate-300">Alamat Email</label>
            <div class="mt-1.5">
                <input id="email" name="email" type="email" autocomplete="email" required
                       value="{{ old('email') }}"
                       class="block w-full rounded-xl bg-slate-950/70 border @error('email') border-rose-500 @else border-slate-700 @enderror px-3.5 py-2.5 text-white placeholder-slate-500 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                       placeholder="admin@ppob.test">
            </div>
            @error('email')
                <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Password Field --}}
        <div>
            <label for="password" class="block text-sm font-medium text-slate-300">Password</label>
            <div class="mt-1.5">
                <input id="password" name="password" type="password" autocomplete="current-password" required
                       class="block w-full rounded-xl bg-slate-950/70 border @error('password') border-rose-500 @else border-slate-700 @enderror px-3.5 py-2.5 text-white placeholder-slate-500 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                       placeholder="••••••••">
            </div>
            @error('password')
                <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Remember Me --}}
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <input id="remember" name="remember" type="checkbox" value="1"
                       class="h-4 w-4 rounded border-slate-700 bg-slate-950 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-slate-900">
                <label for="remember" class="ml-2 block text-sm text-slate-400">Ingat sesi saya</label>
            </div>
        </div>

        {{-- Submit Button --}}
        <div>
            <button type="submit"
                    class="flex w-full justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition-colors">
                Masuk ke Backoffice
            </button>
        </div>
    </form>
</x-admin.layouts.guest>
