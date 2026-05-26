<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Auth\JwtIssuer;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuthenticateJwt
{
    public function __construct(private readonly JwtIssuer $jwtIssuer)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return new JsonResponse([
                'message' => 'Missing bearer token.',
            ], 401);
        }

        try {
            $payload = (array) $this->jwtIssuer->decode($token, 'access');
            $user = User::query()->with('role')->whereKey($payload['sub'] ?? null)->first();
        } catch (Throwable) {
            return new JsonResponse([
                'message' => 'The provided token is invalid or expired.',
            ], 401);
        }

        if (! $user || $user->status !== 'active') {
            return new JsonResponse([
                'message' => 'Authenticated user is unavailable.',
            ], 401);
        }

        auth()->setUser($user);
        $request->attributes->set('auth_payload', $payload);
        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }
}
