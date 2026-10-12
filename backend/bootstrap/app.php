<?php

use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureSpaSession;
use App\Http\Middleware\ValidateBroadcastSubscription;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php', [
        'prefix' => 'api/v1',
        'middleware' => ['api', 'auth:sanctum', EnsureActiveAccount::class, EnsureSpaSession::class, ValidateBroadcastSubscription::class, 'throttle:domain'],
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);
        $middleware->statefulApi();
        $middleware->trimStrings(except: [fn (Request $request): bool => $request->is('api/v1/payments/zalopay/callback')]);
        $middleware->preventRequestForgery(except: ['api/v1/payments/zalopay/callback']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if (! $request->is('api/*')) {
                return $response;
            }
            if ($response->getStatusCode() >= 500) {
                return response()->json(['message' => 'Server Error'], $response->getStatusCode(), $response->headers->all());
            }
            if ($response instanceof JsonResponse) {
                $data = $response->getData(true);
                if (is_array($data)) {
                    unset($data['exception'], $data['file'], $data['line'], $data['trace']);
                    $response->setData($data);
                }
            }

            return $response;
        });
    })->create();
