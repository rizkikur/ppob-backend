<?php

namespace App\Domain\Partner\Http\Middleware;

use App\Domain\Partner\Services\PartnerAuthService;
use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\Http\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Middleware autentikasi partner Open API. */
class PartnerAuthMiddleware
{
    public function __construct(private readonly PartnerAuthService $authService) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $partner = $this->authService->validate($request);
            $request->attributes->set('partner', $partner);

            return $next($request);
        } catch (BusinessException $e) {
            return ApiResponse::error($e->getErrorCode(), $e->getMessage(), $e->getHttpStatus());
        }
    }
}
