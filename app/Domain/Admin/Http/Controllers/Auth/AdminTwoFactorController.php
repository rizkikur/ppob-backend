<?php

namespace App\Domain\Admin\Http\Controllers\Auth;

use App\Domain\Admin\Http\Requests\VerifyTwoFactorRequest;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Services\AdminAuditLogService;
use App\Domain\Admin\Services\AdminTwoFactorService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminTwoFactorController extends Controller
{
    public function __construct(
        protected AdminTwoFactorService $twoFactorService,
        protected AdminAuditLogService $auditLogService
    ) {}

    /**
     * Show 2FA challenge form.
     */
    public function showForm(Request $request): View|RedirectResponse
    {
        /** @var Admin|null $admin */
        $admin = Auth::guard('admin')->user();

        if (! $admin || ! $request->session()->get('admin_2fa_pending', false)) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.two-factor');
    }

    /**
     * Verify 2FA challenge.
     */
    public function verify(VerifyTwoFactorRequest $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        if (! $admin->two_factor_secret) {
            $request->session()->forget('admin_2fa_pending');

            return redirect()->route('admin.dashboard');
        }

        $isValid = $this->twoFactorService->verifyKey(
            $admin->two_factor_secret,
            $request->code
        );

        if (! $isValid) {
            return back()->withErrors([
                'code' => 'Kode autentikasi 2FA salah atau telah kadaluarsa.',
            ]);
        }

        $request->session()->forget('admin_2fa_pending');

        $this->auditLogService->log(
            admin: $admin,
            action: 'admin.2fa_challenge_verified',
            target: $admin,
            reason: 'Verifikasi 2FA berhasil saat login'
        );

        return redirect()->intended(route('admin.dashboard'));
    }
}
