<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyForgotPasswordOtpRequest;
use App\Http\Requests\Auth\VerifyLoginOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserOtp;
use App\Services\MobileOtpService;
use App\Services\SubscriptionAccessService;
use App\Specialties\SpecialtyModuleRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected SubscriptionAccessService $subscriptionAccess,
        protected MobileOtpService $otpService,
        protected SpecialtyModuleRegistry $specialtyModules,
    ) {}

    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();
        $user = $this->findUserByMobile($credentials['mobile'], $credentials['branch_code'] ?? null);

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'mobile' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $this->subscriptionAccess->canLogin($user)) {
            throw ValidationException::withMessages([
                'mobile' => [$this->subscriptionAccess->loginErrorMessage($user)],
            ]);
        }

        $challenge = $this->otpService->issue($user, UserOtp::PURPOSE_LOGIN, $credentials['mobile']);

        return response()->json([
            'message' => 'OTP sent successfully',
            'otp_reference' => $challenge->reference,
            'masked_mobile' => $this->otpService->maskMobile($credentials['mobile']),
            'expires_at' => $challenge->expires_at?->toIso8601String(),
        ]);
    }

    public function verifyLoginOtp(VerifyLoginOtpRequest $request)
    {
        $credentials = $request->validated();
        $user = $this->findUserByMobile($credentials['mobile'], $credentials['branch_code'] ?? null);

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'mobile' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $this->subscriptionAccess->canLogin($user)) {
            throw ValidationException::withMessages([
                'mobile' => [$this->subscriptionAccess->loginErrorMessage($user)],
            ]);
        }

        $challenge = $this->otpService->findChallenge($user, UserOtp::PURPOSE_LOGIN, $credentials['otp_reference'] ?? null);

        if (! $challenge) {
            throw ValidationException::withMessages([
                'otp_reference' => ['The OTP reference is invalid.'],
            ]);
        }

        $this->otpService->verify($challenge, $credentials['otp']);
        $this->otpService->markUsed($challenge);

        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken('api-token')->plainTextToken;

        $user->setAttribute('requires_specialty_selection', $this->requiresSpecialtySelection($user));

        return response()->json([
            'token' => $token,
            'user' => UserResource::make($user->load(['roles', 'permissions', 'specialty', 'company.currentSubscription'])),
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $mobile = $request->validated('mobile');
        $user = $this->findUserByMobile($mobile);

        if (! $user) {
            throw ValidationException::withMessages([
                'mobile' => ['The selected mobile is invalid.'],
            ]);
        }

        $challenge = $this->otpService->issue($user, UserOtp::PURPOSE_FORGOT_PASSWORD, $mobile);

        return response()->json([
            'message' => 'OTP sent successfully',
            'otp_reference' => $challenge->reference,
            'masked_mobile' => $this->otpService->maskMobile($mobile),
            'expires_at' => $challenge->expires_at?->toIso8601String(),
        ]);
    }

    public function verifyForgotPasswordOtp(VerifyForgotPasswordOtpRequest $request)
    {
        $data = $request->validated();
        $user = $this->findUserByMobile($data['mobile']);

        if (! $user) {
            throw ValidationException::withMessages([
                'mobile' => ['The selected mobile is invalid.'],
            ]);
        }

        $challenge = $this->otpService->findChallenge($user, UserOtp::PURPOSE_FORGOT_PASSWORD, $data['otp_reference'] ?? null);

        if (! $challenge) {
            throw ValidationException::withMessages([
                'otp_reference' => ['The OTP reference is invalid.'],
            ]);
        }

        $this->otpService->verify($challenge, $data['otp']);
        $this->otpService->markVerified($challenge);

        return response()->json([
            'message' => 'OTP verified successfully',
            'verified' => true,
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $data = $request->validated();
        $user = $this->findUserByMobile($data['mobile']);

        if (! $user) {
            throw ValidationException::withMessages([
                'mobile' => ['The selected mobile is invalid.'],
            ]);
        }

        $challenge = $this->otpService->findChallenge($user, UserOtp::PURPOSE_FORGOT_PASSWORD, $data['otp_reference']);

        if (! $challenge) {
            throw ValidationException::withMessages([
                'otp_reference' => ['The OTP reference is invalid.'],
            ]);
        }

        if ($challenge->verified_at === null) {
            throw ValidationException::withMessages([
                'otp_reference' => ['The OTP must be verified before resetting the password.'],
            ]);
        }

        if ($challenge->isUsed() || $challenge->isExpired()) {
            throw ValidationException::withMessages([
                'otp_reference' => ['The OTP reference is no longer valid.'],
            ]);
        }

        $user->forceFill(['password' => $data['new_password']])->save();
        $this->otpService->markUsed($challenge);

        return response()->json([
            'message' => 'Password reset successfully',
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->success(null, 'Logged out successfully.');
    }

    public function me(Request $request)
    {
        $user = $request->user()->load(['roles', 'permissions', 'specialty', 'company']);
        $user->setAttribute('requires_specialty_selection', $this->requiresSpecialtySelection($user));

        return $this->success(UserResource::make($user));
    }

    protected function requiresSpecialtySelection(User $user): bool
    {
        if ($user->specialty_id) {
            return false;
        }

        $usableCount = $user->company->activeSpecialties()
            ->filter(fn ($specialty) => $this->specialtyModules->get($specialty->key)?->isBuilt())
            ->count();

        return $usableCount > 1;
    }

    protected function findUserByMobile(string $mobile, ?string $branchCode = null): ?User
    {
        $normalized = $this->otpService->normalizeMobile($mobile);
        $variants = array_values(array_unique(array_filter([
            trim($mobile),
            $normalized,
            '+'.$normalized,
        ])));

        return User::query()
            ->with(['roles', 'permissions', 'company.currentSubscription'])
            ->where(function ($query) use ($variants) {
                foreach ($variants as $variant) {
                    $query->orWhere('phone', $variant);
                }
            })
            ->when($branchCode, function ($query) use ($branchCode) {
                $query->where(function ($branchQuery) use ($branchCode) {
                    $branchQuery->where('branch_name', $branchCode)
                        ->orWhereHas('company', fn ($companyQuery) => $companyQuery->where('code', $branchCode));
                });
            })
            ->orderBy('id')
            ->first();
    }
}
