<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Exceptions\UserAlreadyExistsException;
use App\Domain\Auth\Exceptions\UserNotFoundException;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use Illuminate\Support\Facades\DB;

/**
 * Service autentikasi — register, login, logout.
 *
 * Tanggung jawab:
 * - Registrasi user baru + buat wallet awal
 * - Verifikasi OTP registrasi → terbitkan Sanctum token
 * - Login via OTP → terbitkan Sanctum token
 * - Logout (revoke token)
 */
class AuthService
{
    public function __construct(
        private readonly OtpService $otpService
    ) {}

    public function getOtpService(): OtpService
    {
        return $this->otpService;
    }

    /**
     * Daftar user baru.
     *
     * @param  array  $data  Berisi: phone, name, user_tier_id (opsional)
     *
     * @throws UserAlreadyExistsException jika phone sudah terdaftar
     */
    public function register(array $data): User
    {
        // Cek apakah phone sudah ada
        if (User::where('phone', $data['phone'])->exists()) {
            throw new UserAlreadyExistsException;
        }

        // Ambil tier default jika tidak diisi
        $tierInput = $data['user_tier_id'] ?? null;
        if ($tierInput) {
            $tier = UserTier::find($tierInput)
                ?? UserTier::where('name', $tierInput)->firstOrFail();
        } else {
            $tier = UserTier::where('name', 'end_user')->first()
                ?? UserTier::orderBy('id')->first()
                ?? UserTier::create(['name' => 'end_user', 'label' => 'Pengguna Akhir', 'description' => 'Pelanggan regular']);
        }

        $user = DB::transaction(function () use ($data, $tier) {
            $user = User::create([
                'user_tier_id' => $tier->id,
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'role' => 'user',
                'is_active' => true,
                'is_verified' => false,
            ]);

            // Buat wallet awal (langsung insert, WalletService belum ada)
            DB::table('wallets')->insert([
                'user_id' => $user->id,
                'balance_cents' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $user;
        });

        // Kirim OTP registrasi
        $this->otpService->send($user->phone, 'register');

        return $user;
    }

    /**
     * Verifikasi OTP registrasi → tandai verified + terbitkan token.
     *
     * @return array{user: User, token: string}
     */
    public function verifyRegistration(string $phone, string $code): array
    {
        $this->otpService->verify($phone, $code, 'register');

        $user = User::where('phone', $phone)->firstOrFail();
        $user->update(['is_verified' => true]);

        $token = $user->createToken('mobile');

        return [
            'user' => $user->fresh(['tier']),
            'token' => $token->plainTextToken,
        ];
    }

    /**
     * Kirim OTP untuk login.
     *
     * @throws UserNotFoundException jika user tidak ditemukan atau tidak aktif/verified
     */
    public function requestLoginOtp(string $phone): void
    {
        $user = User::where('phone', $phone)->first();

        if (! $user || ! $user->is_active || ! $user->is_verified) {
            throw new UserNotFoundException('Akun tidak ditemukan atau belum diverifikasi.');
        }

        $this->otpService->send($phone, 'login');
    }

    /**
     * Login via OTP → verifikasi + terbitkan token.
     *
     * @return array{user: User, token: string}
     *
     * @throws UserNotFoundException jika user tidak aktif
     */
    public function login(string $phone, string $code): array
    {
        $this->otpService->verify($phone, $code, 'login');

        $user = User::where('phone', $phone)->firstOrFail();

        if (! $user->is_active) {
            throw new UserNotFoundException('Akun Anda telah dinonaktifkan.');
        }

        $token = $user->createToken('mobile');

        return [
            'user' => $user->fresh(['tier']),
            'token' => $token->plainTextToken,
        ];
    }

    /**
     * Logout: revoke token yang sedang digunakan.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    // ─── Metode lama (backward-compat) ────────────────────

    /**
     * Cari user berdasarkan nomor telepon.
     *
     * @throws UserNotFoundException jika tidak ditemukan atau tidak aktif
     */
    public function findByPhone(string $phone): User
    {
        $user = User::where('phone', $phone)->first();

        if (! $user) {
            throw new UserNotFoundException('Akun dengan nomor ini tidak ditemukan.');
        }

        if (! $user->is_active) {
            throw new UserNotFoundException('Akun Anda telah dinonaktifkan.');
        }

        return $user;
    }

    /**
     * Terbitkan Sanctum token dan tandai user sebagai verified.
     *
     * @return array{user: User, token: string}
     */
    public function issueToken(User $user, string $deviceName = 'mobile'): array
    {
        if (! $user->is_verified) {
            $user->update(['is_verified' => true]);
        }

        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'user' => $user->fresh(['tier']),
            'token' => $token,
        ];
    }

    /**
     * Logout dari semua device.
     */
    public function logoutAll(User $user): void
    {
        $user->tokens()->delete();
    }
}
