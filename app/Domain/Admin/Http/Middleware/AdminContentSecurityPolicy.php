<?php

namespace App\Domain\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class AdminContentSecurityPolicy
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);
        View::share('csp_nonce', $nonce);
        Vite::useCspNonce($nonce);

        /** @var Response $response */
        $response = $next($request);

        $cspHeader = "default-src 'self'; "
            ."script-src 'self' 'unsafe-eval' 'nonce-{$nonce}' https://cdn.jsdelivr.net; "
            ."style-src 'self' 'unsafe-inline' https://fonts.bunny.net; "
            ."font-src 'self' https://fonts.bunny.net; "
            ."img-src 'self' data: https:; "
            ."connect-src 'self' https://cdn.jsdelivr.net; "
            ."frame-ancestors 'none'; "
            ."base-uri 'self'; "
            ."form-action 'self';";

        $response->headers->set('Content-Security-Policy', $cspHeader);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
