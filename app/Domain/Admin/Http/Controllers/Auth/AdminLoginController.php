<?php

namespace App\Domain\Admin\Http\Controllers\Auth;

use App\Domain\Admin\Http\Requests\AdminLoginRequest;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Services\AdminAuditLogService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AdminLoginController extends Controller
{
    public function __construct(
        protected AdminAuditLogService $auditLogService
    ) {}

    /**
     * Show admin login form.
     */
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        // Clean up intended URL if it points to non-admin pages (e.g. /docs or /)
        $prefix = config('admin.prefix', 'office');
        $intended = session()->get('url.intended');
        if ($intended && ! str_contains($intended, "/{$prefix}") && ! str_contains($intended, '/admin')) {
            session()->forget('url.intended');
        }

        return view('admin.auth.login');
    }

    /**
     * Handle admin login attempt.
     */
    public function login(AdminLoginRequest $request): RedirectResponse
    {
        $throttleKey = 'admin-login:'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withInput($request->only('email', 'remember'))->withErrors([
                'email' => "Terlalu banyak percobaan login. Silakan coba lagi dalam {$seconds} detik.",
            ]);
        }

        $credentials = [
            'email' => $request->email,
            'password' => $request->password,
        ];

        // Check active status
        $admin = Admin::where('email', $request->email)->first();

        if ($admin && ! $admin->is_active) {
            RateLimiter::hit($throttleKey, 300);

            return back()->withInput($request->only('email', 'remember'))->withErrors([
                'email' => 'Akun admin Anda dinonaktifkan. Hubungi Super Administrator.',
            ]);
        }

        if (! Auth::guard('admin')->attempt(array_merge($credentials, ['is_active' => true]), $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 300);

            return back()->withInput($request->only('email', 'remember'))->withErrors([
                'email' => 'Email atau password yang Anda masukkan salah.',
            ]);
        }

        // Login successful
        RateLimiter::clear($throttleKey);

        /** @var Admin $authenticatedAdmin */
        $authenticatedAdmin = Auth::guard('admin')->user();

        // Prevent session fixation
        $request->session()->regenerate();

        // Update login metadata
        $authenticatedAdmin->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        // Audit trail
        $this->auditLogService->log(
            admin: $authenticatedAdmin,
            action: 'admin.login',
            target: $authenticatedAdmin,
            reason: 'Login via Backoffice Panel'
        );

        // Check if 2FA is active
        if ($authenticatedAdmin->hasTwoFactor()) {
            $request->session()->put('admin_2fa_pending', true);

            return redirect()->route('admin.two-factor.form');
        }

        $prefix = config('admin.prefix', 'office');
        $intended = $request->session()->get('url.intended');
        $isAdminIntended = $intended && (str_contains($intended, "/{$prefix}") || str_contains($intended, '/admin')) && ! str_contains($intended, '/login');

        if (! $isAdminIntended) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    /**
     * Handle admin logout.
     */
    public function logout(Request $request): RedirectResponse
    {
        /** @var Admin|null $admin */
        $admin = Auth::guard('admin')->user();

        if ($admin) {
            $this->auditLogService->log(
                admin: $admin,
                action: 'admin.logout',
                target: $admin,
                reason: 'Logout dari Backoffice'
            );
        }

        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('success', 'Anda telah berhasil logout.');
    }
}
