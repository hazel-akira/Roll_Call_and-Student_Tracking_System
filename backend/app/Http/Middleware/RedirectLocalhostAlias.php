<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectLocalhostAlias
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('local') || $request->getHost() !== '127.0.0.1') {
            return $next($request);
        }

        return redirect()->away(str_replace('127.0.0.1', 'localhost', $request->fullUrl()));
    }
}
