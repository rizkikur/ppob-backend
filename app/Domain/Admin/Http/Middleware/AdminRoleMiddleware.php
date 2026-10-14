<?php

namespace App\Domain\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminRoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $admin = $request->user('admin');

        if (! $admin) {
            return redirect()->guest(route('admin.login'));
        }

        if (empty($roles)) {
            return $next($request);
        }

        $adminRole = $admin->role instanceof \BackedEnum ? $admin->role->value : (string) $admin->role;

        if (! in_array($adminRole, $roles, true)) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk halaman ini.');
        }

        return $next($request);
    }
}
