<?php

namespace App\Domain\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorAuthenticated
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('admin_2fa_pending', false)) {
            if (! $request->routeIs('admin.two-factor.*') && ! $request->routeIs('admin.logout')) {
                return redirect()->route('admin.two-factor.form');
            }
        }

        return $next($request);
    }
}
