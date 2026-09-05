<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Intercepta qualquer exceção e exibe na tela brutalmente para debug
        $exceptions->render(function (\Throwable $e, Request $request) {
            echo "<h1>CRITICAL ERROR TRACE (KERNEL)</h1>";
            echo "<b>Message:</b> " . $e->getMessage() . "<br><br>";
            echo "<b>File:</b> " . $e->getFile() . ":" . $e->getLine() . "<br><br>";
            echo "<b>Trace:</b><br><pre>" . $e->getTraceAsString() . "</pre>";
            exit(1);
        });
    })
    ->booted(function () {
        // ── Rate Limiter para chamadas à IA (20 req/min por IP) ────────────
        RateLimiter::for('ai_chat', function (Request $request) {
            $limit = (int) config('ai.rate_limit_per_minute', 20);
            return Limit::perMinute($limit)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => 'Limite de 20 requisições por minuto atingido para este endereço IP. Por favor, aguarde.',
                    ], 429, $headers);
                });
        });
    })
    ->create();