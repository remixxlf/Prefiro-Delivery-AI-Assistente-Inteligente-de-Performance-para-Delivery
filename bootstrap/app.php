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
        // Tratamento seguro de exceções para endpoints de API
        // Garante que NENHUM stack trace ou erro interno seja vazado para o usuário
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                // Erros de Validação (Form Requests)
                if ($e instanceof ValidationException) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => 'Dados da requisição inválidos.',
                        'errors'  => $e->errors(),
                    ], 422);
                }

                // Erros HTTP (inclui 429 Too Many Requests e 404 Not Found)
                if ($e instanceof HttpExceptionInterface) {
                    $statusCode = $e->getStatusCode();
                    $message = $statusCode === 429
                        ? 'Limite de requisições excedido. Por favor, aguarde antes de enviar uma nova pergunta.'
                        : ($statusCode === 404 ? 'Recurso da API não encontrado.' : $e->getMessage());

                    return response()->json([
                        'status'  => 'error',
                        'message' => $message,
                    ], $statusCode);
                }

                // Registra log detalhado internamente no canal dedicado de erros de IA
                try {
                    Log::channel('ai_errors')->error('Exceção na API capturada com segurança: ' . $e->getMessage(), [
                        'exception_class' => get_class($e),
                        'path'            => $request->path(),
                        'method'          => $request->method(),
                        'ip'              => $request->ip(),
                        'file'            => $e->getFile(),
                        'line'            => $e->getLine(),
                    ]);
                } catch (\Throwable $logError) {
                    // Fallback se monolog falhar
                    Log::error('Exceção na API: ' . $e->getMessage());
                }

                // Resposta opaca e segura para produção (HTTP 500 sem stack trace)
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Ocorreu um erro interno ao processar sua solicitação. Tente novamente mais tarde.',
                ], 500);
            }
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