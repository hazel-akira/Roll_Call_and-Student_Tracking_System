<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ResolveMicrosoftUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MicrosoftExchangeRequest;
use App\Http\Resources\UserResource;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\JwtIssuer;
use App\Services\Auth\MicrosoftTokenValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MicrosoftAuthController extends Controller
{
    public function __construct(
        private readonly MicrosoftTokenValidator $tokenValidator,
        private readonly ResolveMicrosoftUser $resolveMicrosoftUser,
        private readonly JwtIssuer $jwtIssuer,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function exchange(MicrosoftExchangeRequest $request): JsonResponse
    {
        $claims = $this->tokenValidator->validate(
            $request->string('id_token')->toString(),
            $request->string('nonce')->toString() ?: null,
        );

        $user = $this->resolveMicrosoftUser->execute($claims);
        $tokens = $this->jwtIssuer->createTokenPair($user, $request);

        $this->auditLogger->log(
            $user,
            'auth.microsoft.exchange',
            'User authenticated via Microsoft SSO.',
            $user,
            [],
            ['tenant_id' => $claims['tid'] ?? null],
            $request,
        );

        return response()->json([
            'message' => 'Microsoft sign-in completed successfully.',
            'user' => UserResource::make($user),
            'tokens' => $tokens,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => UserResource::make($request->user()->loadMissing(['role', 'identities'])),
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $tokens = $this->jwtIssuer->rotateRefreshToken($validated['refresh_token'], $request);

        return response()->json([
            'message' => 'Access token refreshed successfully.',
            'tokens' => $tokens,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $refreshToken = $request->input('refresh_token');
        $this->jwtIssuer->revokeRefreshToken(is_string($refreshToken) ? $refreshToken : null);

        $this->auditLogger->log(
            $request->user(),
            'auth.logout',
            'User signed out from the platform.',
            $request->user(),
            request: $request,
        );

        return response()->json([
            'message' => 'Logout completed successfully.',
        ]);
    }
}
