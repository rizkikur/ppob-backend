<?php

namespace App\Domain\Auth\Http\Controllers;

use App\Domain\Auth\Exceptions\UserAlreadyExistsException;
use App\Domain\Auth\Http\Requests\LogoutRequest;
use App\Domain\Auth\Http\Requests\SendOtpRequest;
use App\Domain\Auth\Http\Requests\VerifyOtpRequest;
use App\Domain\Auth\Http\Resources\TokenResource;
use App\Domain\Auth\Http\Resources\UserResource;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Services\AuthService;
use App\Domain\Shared\Http\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Controller autentikasi — OTP, register, verify, login, logout, me.
 *
 * Routes (sesuai spec Phase 1):
 *   POST /auth/otp            → sendOtp       (register atau login)
 *   POST /auth/register       → register      (daftar user baru)
 *   POST /auth/verify-register→ verifyRegister (verifikasi OTP registrasi → token)
 *   POST /auth/login          → login         (verifikasi OTP login → token)
 *   POST /auth/logout         → logout        (revoke token, requires auth)
 *   GET  /auth/me             → me            (data user, requires auth)
 *
 * Semua response menggunakan metode dari ApiController (bukan response()->json() mentah).
 */
class AuthController extends ApiController
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    /**
     * POST /auth/otp
     * Kirim OTP untuk register atau login.
     */
    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        $data = $request->validated();
        $phone = $data['phone'];
        $purpose = $data['purpose'] ?? $data['type'] ?? 'login';

        if ($purpose === 'register') {
            if (User::where('phone', $phone)->exists()) {
                throw new UserAlreadyExistsException;
            }
            $this->authService->getOtpService()->send($phone, 'register');
        } else {
            // Untuk login: pastikan user ada dan aktif
            $this->authService->requestLoginOtp($phone);
        }

        return $this->success(['message' => 'OTP telah dikirim.']);
    }

    /**
     * POST /auth/register
     * Daftar user baru + kirim OTP verifikasi.
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^(\+62|0)8[0-9]{8,12}$/'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
        ]);

        $user = $this->authService->register($request->only('phone', 'name', 'email'));

        return $this->created([
            'message' => 'Akun berhasil dibuat. OTP telah dikirim ke WhatsApp Anda.',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * POST /auth/verify-register
     * Verifikasi OTP registrasi → dapat Sanctum token.
     */
    public function verifyRegister(VerifyOtpRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->authService->verifyRegistration($data['phone'], $data['code']);

        return $this->success(new TokenResource($result));
    }

    /**
     * POST /auth/login
     * Login: verifikasi OTP → dapat Sanctum token.
     */
    public function login(VerifyOtpRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->authService->login($data['phone'], $data['code']);

        return $this->success(new TokenResource($result));
    }

    /**
     * POST /auth/otp/verify (OpenAPI compatibility)
     */
    public function otpVerify(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
            'type' => ['sometimes', 'string', 'in:register,login'],
        ]);

        $type = $request->input('type', 'login');
        if ($type === 'register') {
            $result = $this->authService->verifyRegistration($request->input('phone'), $request->input('code'));
        } else {
            $result = $this->authService->login($request->input('phone'), $request->input('code'));
        }

        return $this->success(new TokenResource($result));
    }

    /**
     * POST /auth/logout
     * Revoke token yang sedang digunakan.
     */
    public function logout(LogoutRequest $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->noContent();
    }

    /**
     * GET /auth/me
     * Data user yang sedang login.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success(new UserResource($request->user()->load('tier')));
    }

    /**
     * PUT /auth/profile
     * Perbarui data profil user (nama & email).
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        $user->update($validated);

        return $this->success(
            new UserResource($user->fresh(['tier'])),
            'Profil berhasil diperbarui'
        );
    }

    /**
     * POST /auth/fcm-token
     * Daftarkan atau perbarui device push notification token (FCM).
     */
    public function updateFcmToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->update([
            'fcm_token' => $validated['fcm_token'],
        ]);

        return $this->success([
            'fcm_token' => $validated['fcm_token'],
        ], 'Token notifikasi berhasil didaftarkan');
    }
}
