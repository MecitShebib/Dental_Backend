<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyForgotPasswordOtpRequest;
use App\Http\Requests\Auth\VerifyLoginOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserOtp;
use App\Services\MobileOtpService;
use App\Services\SubscriptionAccessService;
use App\Specialties\SpecialtyModuleRegistry;
use App\Support\LegalContent;
use Illuminate\Http\JsonResponse;
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

        // While a fixed testing OTP is configured, every challenge resolves
        // to the same known code -- it verifies nothing, so skip the OTP
        // screen entirely and log the user straight in on password alone.
        if ($this->otpService->usesFixedCode()) {
            return $this->authenticatedSessionResponse($user);
        }

        $challenge = $this->otpService->issue($user, UserOtp::PURPOSE_LOGIN, $credentials['mobile']);

        return response()->json([
            'message' => 'OTP sent successfully',
            'otp_reference' => $challenge->reference,
            'masked_mobile' => $this->otpService->maskMobile($credentials['mobile']),
            ...$this->otpDestinationFields($user, $credentials['mobile']),
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

        return $this->authenticatedSessionResponse($user);
    }

    /**
     * Shared by verifyLoginOtp() and login()'s fixed-OTP bypass -- both end
     * the login flow the same way once the user's identity is settled
     * (password alone, in the bypass case).
     */
    protected function authenticatedSessionResponse(User $user): JsonResponse
    {
        $user->forceFill(['last_login_at' => now()])->save();

        // Single-session-per-account: logging in from a new device/browser
        // must sign the previous one out. Only revokes prior LOGIN tokens
        // (name 'api-token') -- deliberately leaves Settings > API Token's
        // named integration tokens alone (see ApiTokenController).
        $user->tokens()->where('name', 'api-token')->delete();
        // Login tokens expire (LOGIN_TOKEN_DAYS, default 30) -- integration
        // tokens from Settings > API Token are created without an expiry.
        $token = $user->createToken('api-token', ['*'], now()->addDays((int) config('services.auth.login_token_days', 30)))->plainTextToken;

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
            ...$this->otpDestinationFields($user, $mobile),
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

    /**
     * The signed-in person accepts the current Terms of Service, including
     * the personal-account clause (no sharing; every action under this
     * account is attributed to them). Must be the current version -- a stale
     * SPA tab can't accept text the user never saw. Logged to the audit log
     * with IP/user agent as the evidentiary record of acceptance.
     */
    public function acceptTerms(Request $request)
    {
        $validated = $request->validate([
            'version' => ['required', 'string', 'in:'.LegalContent::TERMS_VERSION],
            'personal_account_confirmed' => ['required', 'accepted'],
        ]);

        $user = $request->user();
        // Quietly: the explicit 'terms_accepted' audit entry below is the
        // meaningful record; the generic 'updated' one would just duplicate it.
        $user->forceFill([
            'terms_accepted_version' => $validated['version'],
            'terms_accepted_at' => now(),
        ])->saveQuietly();

        AuditLog::record('terms_accepted', $user, $user, ['version' => $validated['version']]);

        return $this->me($request);
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

    /**
     * @return array{otp_channel: string, masked_destination: string}
     */
    protected function otpDestinationFields(User $user, string $mobile): array
    {
        $channel = config('services.otp.channel', 'sms');

        return [
            'otp_channel' => $channel,
            'masked_destination' => $channel === 'email'
                ? $this->otpService->maskEmail($user->email)
                : $this->otpService->maskMobile($mobile),
        ];
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
