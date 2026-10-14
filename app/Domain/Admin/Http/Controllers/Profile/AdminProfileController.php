<?php

namespace App\Domain\Admin\Http\Controllers\Profile;

use App\Domain\Admin\Http\Requests\UpdateProfileRequest;
use App\Domain\Admin\Http\Requests\VerifyTwoFactorRequest;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Services\AdminAuditLogService;
use App\Domain\Admin\Services\AdminTwoFactorService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AdminProfileController extends Controller
{
    public function __construct(
        protected AdminTwoFactorService $twoFactorService,
        protected AdminAuditLogService $auditLogService
    ) {}

    /**
     * Show admin profile page.
     */
    public function index(): View
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        return view('admin.profile.index', [
            'admin' => $admin,
        ]);
    }

    /**
     * Update admin profile info.
     */
    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        $oldData = [
            'name' => $admin->name,
            'email' => $admin->email,
        ];

        $admin->name = $request->name;
        $admin->email = $request->email;

        if ($request->filled('password')) {
            $admin->password = Hash::make($request->password);
        }

        $admin->save();

        $this->auditLogService->log(
            admin: $admin,
            action: 'admin.profile_updated',
            target: $admin,
            changes: [
                'old' => $oldData,
                'new' => ['name' => $admin->name, 'email' => $admin->email],
                'password_changed' => $request->filled('password'),
            ],
            reason: 'Perubahan data profil admin mandiri'
        );

        return back()->with('success', 'Profil Anda berhasil diperbarui.');
    }

    /**
     * Initiate 2FA setup.
     */
    public function enableTwoFactor(Request $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        if ($admin->hasTwoFactor()) {
            return back()->with('info', '2FA sudah aktif pada akun Anda.');
        }

        $secret = $this->twoFactorService->generateSecretKey(16);
        $request->session()->put('admin_2fa_draft_secret', $secret);

        return redirect()->route('admin.profile.2fa.setup');
    }

    /**
     * Show 2FA setup QR & code input page.
     */
    public function showTwoFactorSetup(Request $request): View|RedirectResponse
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        $secret = $request->session()->get('admin_2fa_draft_secret');

        if (! $secret || $admin->hasTwoFactor()) {
            return redirect()->route('admin.profile');
        }

        $otpAuthUri = $this->twoFactorService->getOtpAuthUri(
            company: config('app.name', 'PPOB Admin'),
            holder: $admin->email,
            secret: $secret
        );

        return view('admin.profile.two-factor-setup', [
            'admin' => $admin,
            'secret' => $secret,
            'otpAuthUri' => $otpAuthUri,
        ]);
    }

    /**
     * Confirm 2FA setup with OTP code.
     */
    public function confirmTwoFactor(VerifyTwoFactorRequest $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();
        $secret = $request->session()->get('admin_2fa_draft_secret');

        if (! $secret) {
            return redirect()->route('admin.profile')->withErrors([
                '2fa' => 'Sesi setup 2FA kadaluarsa. Silakan mulai ulang.',
            ]);
        }

        if (! $this->twoFactorService->verifyKey($secret, $request->code)) {
            return back()->withErrors([
                'code' => 'Kode autentikasi salah atau kadaluarsa. Coba kode terbaru di aplikasi Anda.',
            ]);
        }

        $admin->two_factor_secret = $secret;
        $admin->two_factor_confirmed_at = now();
        $admin->save();

        $request->session()->forget('admin_2fa_draft_secret');

        $this->auditLogService->log(
            admin: $admin,
            action: 'admin.2fa_enabled',
            target: $admin,
            reason: 'Aktivasi 2FA via Google Authenticator'
        );

        return redirect()->route('admin.profile')->with('success', 'Two-Factor Authentication (2FA) berhasil diaktifkan.');
    }

    /**
     * Disable 2FA.
     */
    public function disableTwoFactor(Request $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        $admin->two_factor_secret = null;
        $admin->two_factor_confirmed_at = null;
        $admin->save();

        $this->auditLogService->log(
            admin: $admin,
            action: 'admin.2fa_disabled',
            target: $admin,
            reason: 'Deaktivasi 2FA mandiri'
        );

        return redirect()->route('admin.profile')->with('success', 'Two-Factor Authentication berhasil dinonaktifkan.');
    }
}
