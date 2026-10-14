<?php

namespace App\Domain\Admin\Http\Controllers\Security;

use App\Domain\Admin\Http\Requests\ConfirmAdminPasswordRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ConfirmPasswordController extends Controller
{
    /**
     * Show password confirmation prompt.
     */
    public function showConfirmForm(): View
    {
        return view('admin.auth.confirm-password');
    }

    /**
     * Confirm password and set session timestamp.
     */
    public function confirm(ConfirmAdminPasswordRequest $request): RedirectResponse
    {
        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended(route('admin.dashboard'));
    }
}
