<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateBroadcastSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->isMethod('post'), 405);
        $request->validate(['socket_id' => ['required', 'string', 'max:100', 'regex:/^[0-9]+\.[0-9]+$/D'], 'channel_name' => ['required', 'string', 'max:100', 'regex:/^private-user\.[1-9][0-9]*$/D']]);
        abort_if(array_diff(array_keys($request->all()), ['socket_id', 'channel_name']), 422, 'Unexpected subscription fields.');

        return $next($request);
    }
}
