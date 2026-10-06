<?php

namespace App\Domain\Security\Http\Controllers;

use App\Domain\Security\Http\Requests\ChangePinRequest;
use App\Domain\Security\Http\Requests\PinChallengeRequest;
use App\Domain\Security\Http\Requests\PinVerifyRequest;
use App\Domain\Security\Http\Requests\SetPinRequest;
use App\Domain\Security\Http\Resources\PinTokenResource;
use App\Domain\Security\Services\PinService;
use App\Domain\Shared\Http\ApiController;
use Illuminate\Http\JsonResponse;

/**
 * Controller untuk manajemen PIN.
 *
 * Alur wajib (lihat docs/security-design.md bagian 2):
 * POST /security/pin/challenge → POST /security/pin/verify → gunakan X-Pin-Token
 */
class PinController extends ApiController
{
    public function __construct(
        private readonly PinService $pinService
    ) {}

    /**
     * POST /security/pin
     * Set PIN pertama kali.
     */
    public function setPin(SetPinRequest $request): JsonResponse
    {
        $this->pinService->setPin($request->user(), $request->validated('pin'));

        return $this->success(['message' => 'PIN berhasil diatur.']);
    }

    /**
     * PUT /security/pin
     * Ganti PIN (perlu X-Pin-Token dari PIN lama).
     * Middleware PinTokenMiddleware sudah memvalidasi token sebelum sampai sini.
     */
    public function changePin(ChangePinRequest $request): JsonResponse
    {
        $this->pinService->changePin($request->user(), $request->validated('new_pin'));

        return $this->success(['message' => 'PIN berhasil diubah.']);
    }

    /**
     * POST /security/pin/challenge
     * Step 1: Minta tantangan PIN.
     */
    public function challenge(PinChallengeRequest $request): JsonResponse
    {
        $challengeId = $this->pinService->challenge(
            $request->user(),
            $request->validated('purpose')
        );

        return $this->success(['challenge_id' => $challengeId]);
    }

    /**
     * POST /security/pin/verify
     * Step 2: Verifikasi PIN dan dapatkan pin_verification_token.
     */
    public function verify(PinVerifyRequest $request): JsonResponse
    {
        $data = $request->validated();
        $token = $this->pinService->verify(
            $request->user(),
            $data['challenge_id'],
            $data['pin'],
            $request->input('purpose')
        );

        return $this->success(new PinTokenResource($token));
    }
}
