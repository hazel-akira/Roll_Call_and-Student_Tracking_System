<?php

namespace App\Services\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class JwtIssuer
{
    public function createTokenPair(User $user, ?Request $request = null): array
    {
        $accessToken = $this->issueToken($user, 'access', (int) config('jwt.ttl'));
        $refreshPayload = $this->issueToken($user, 'refresh', (int) config('jwt.refresh_ttl'));

        RefreshToken::query()->create([
            'user_id' => $user->id,
            'token_id' => $refreshPayload['jti'],
            'expires_at' => now()->addSeconds((int) config('jwt.refresh_ttl')),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);

        return [
            'access_token' => $accessToken['token'],
            'refresh_token' => $refreshPayload['token'],
            'token_type' => 'Bearer',
            'expires_in' => (int) config('jwt.ttl'),
            'refresh_expires_in' => (int) config('jwt.refresh_ttl'),
        ];
    }

    public function decode(string $token, string $expectedType = 'access'): object
    {
        try {
            $payload = JWT::decode($token, new Key($this->secret(), (string) config('jwt.algo')));
        } catch (ExpiredException) {
            throw ValidationException::withMessages([
                'token' => 'The token has expired.',
            ]);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'token' => 'The token could not be decoded.',
            ]);
        }

        if (($payload->type ?? null) !== $expectedType) {
            throw ValidationException::withMessages([
                'token' => 'The token type is invalid for this endpoint.',
            ]);
        }

        return $payload;
    }

    public function rotateRefreshToken(string $refreshToken, ?Request $request = null): array
    {
        $payload = (array) $this->decode($refreshToken, 'refresh');

        $record = RefreshToken::query()
            ->where('token_id', $payload['jti'] ?? null)
            ->whereNull('revoked_at')
            ->first();

        if (! $record || $record->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'refresh_token' => 'The refresh token is invalid or has expired.',
            ]);
        }

        $user = User::query()->with('role')->find($payload['sub'] ?? null);

        if (! $user || $user->status !== 'active') {
            throw ValidationException::withMessages([
                'refresh_token' => 'The user account is not active.',
            ]);
        }

        $record->forceFill([
            'revoked_at' => now(),
            'last_used_at' => now(),
        ])->save();

        return $this->createTokenPair($user, $request);
    }

    public function revokeRefreshToken(?string $refreshToken): void
    {
        if (! $refreshToken) {
            return;
        }

        $payload = (array) $this->decode($refreshToken, 'refresh');

        RefreshToken::query()
            ->where('token_id', $payload['jti'] ?? null)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'last_used_at' => now(),
            ]);
    }

    private function issueToken(User $user, string $type, int $ttl): array
    {
        $issuedAt = now();
        $tokenId = (string) Str::uuid();
        $payload = [
            'iss' => (string) config('jwt.issuer'),
            'sub' => (string) $user->getKey(),
            'iat' => $issuedAt->timestamp,
            'nbf' => $issuedAt->timestamp,
            'exp' => $issuedAt->copy()->addSeconds($ttl)->timestamp,
            'jti' => $tokenId,
            'type' => $type,
            'role' => $user->role?->slug,
        ];

        return [
            'jti' => $tokenId,
            'token' => JWT::encode($payload, $this->secret(), (string) config('jwt.algo')),
        ];
    }

    private function secret(): string
    {
        $secret = (string) config('jwt.secret');

        if (str_starts_with($secret, 'base64:')) {
            return base64_decode(substr($secret, 7), true) ?: substr($secret, 7);
        }

        return $secret;
    }
}
