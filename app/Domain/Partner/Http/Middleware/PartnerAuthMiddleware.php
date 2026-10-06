<?php

namespace App\Domain\Partner\Http\Middleware;

use App\Domain\Partner\Models\PartnerLog;
use App\Domain\Partner\Services\PartnerAuthService;
use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\Http\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Middleware autentikasi partner Open API dengan audit logging ke partner_logs. */
class PartnerAuthMiddleware
{
    public function __construct(private readonly PartnerAuthService $authService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $partner = null;

        try {
            $partner = $this->authService->validate($request);
            $request->attributes->set('partner', $partner);

            // Set user konteks ke user representasi partner untuk dompet & idempotency
            $partnerUser = $partner->getOrCreateUser();
            Auth::setUser($partnerUser);

            /** @var Response $response */
            $response = $next($request);

            $this->logRequest($partner, $request, $response->getStatusCode(), $startTime);

            return $response;
        } catch (BusinessException $e) {
            $response = ApiResponse::error($e->getErrorCode(), $e->getMessage(), $e->getHttpStatus());

            $partner = $partner ?? $request->attributes->get('partner');
            if ($partner) {
                $this->logRequest($partner, $request, $e->getHttpStatus(), $startTime);
            }

            return $response;
        } catch (\Throwable $e) {
            $partner = $partner ?? $request->attributes->get('partner');
            if ($partner) {
                $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                $this->logRequest($partner, $request, $status, $startTime);
            }

            throw $e;
        }
    }

    private function logRequest($partner, Request $request, int $statusCode, float $startTime): void
    {
        try {
            $durationMs = (int) max(1, round((microtime(true) - $startTime) * 1000));

            PartnerLog::create([
                'partner_id' => $partner->id,
                'endpoint' => $request->path(),
                'method' => $request->method(),
                'request_body' => $request->all(),
                'response_code' => $statusCode,
                'duration_ms' => $durationMs,
                'ip_address' => $request->ip() ?? '127.0.0.1',
            ]);
        } catch (\Throwable) {
            // Logging audit jangan sampai menggagalkan response
        }
    }
}
