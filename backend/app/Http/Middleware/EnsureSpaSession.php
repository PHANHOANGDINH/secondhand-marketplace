<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSpaSession
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->attributes->get('sanctum') && $request->hasSession(), 419, 'A first-party session is required.');

        return $next($request);
    }
}
