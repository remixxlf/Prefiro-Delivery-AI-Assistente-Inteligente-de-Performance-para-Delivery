<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChatAskRequest;
use App\Services\AI\AIService;
use App\Models\AiConversation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Log;

/**
 * ChatController
 *
 * Endpoints RESTful para interação conversacional com o Assistente de IA.
 * Suporta requisições síncronas padrão (JSON) e streaming contínuo (Server-Sent Events).
 */
class ChatController extends Controller
{
    public function __construct(
        protected AIService $aiService
    ) {}

    /**
     * Envia pergunta e retorna a resposta analítica consolidada em JSON.
     * POST /api/v1/chat
     */
    public function ask(ChatAskRequest $request): JsonResponse
    {
        try {
            $question  = $request->validated('question');
            $sessionId = $request->validated('session_id');

            $result = $this->aiService->ask($question, $sessionId);

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'session_id'   => $result['session_id'],
                    'intent'       => $result['intent'],
                    'response'     => $result['response'],
                    'context_data' => $result['context_data'],
                    'provider'     => $result['provider'],
                    'model'        => $result['model'],
                    'tokens'       => $result['tokens'],
                    'cost_usd'     => $result['cost_usd'],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error("ChatController@ask falhou: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Ocorreu uma falha ao processar sua pergunta. Por favor, tente novamente.',
            ], 500);
        }
    }

    /**
     * Envia pergunta e transmite a resposta em tempo real via Server-Sent Events (SSE).
     * POST /api/v1/chat/stream
     */
    public function stream(ChatAskRequest $request): StreamedResponse
    {
        $question  = $request->validated('question');
        $sessionId = $request->validated('session_id');

        return response()->stream(function () use ($question, $sessionId) {
            // Desativa buffering do PHP para envio imediato de cada chunk
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            try {
                $this->aiService->stream($question, $sessionId, function (string $chunk) {
                    echo "event: chunk\n";
                    echo "data: " . json_encode(['content' => $chunk], JSON_UNESCAPED_UNICODE) . "\n\n";
                    flush();
                });

                echo "event: done\n";
                echo "data: " . json_encode(['status' => 'completed']) . "\n\n";
                flush();
            } catch (\Throwable $e) {
                Log::error("ChatController@stream falhou: {$e->getMessage()}");
                echo "event: error\n";
                echo "data: " . json_encode([
                    'message' => 'Erro durante a transmissão da resposta.'
                ]) . "\n\n";
                flush();
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream; charset=UTF-8',
            'Cache-Control'     => 'no-cache, no-store, must-revalidate',
            'Connection'        => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Desativa buffer do Nginx
        ]);
    }

    /**
     * Retorna o histórico de conversas da sessão atual.
     * GET /api/v1/chat/history
     */
    public function history(Request $request): JsonResponse
    {
        $sessionId = $request->query('session_id');

        if (!$sessionId) {
            return response()->json([
                'status'  => 'success',
                'data'    => [],
                'message' => 'Nenhum session_id informado.',
            ]);
        }

        $conversations = AiConversation::forSession($sessionId)->get();

        $timeline = [];
        foreach ($conversations as $conv) {
            // Mensagem enviada pelo gestor
            $timeline[] = [
                'id'         => $conv->id . '_user',
                'sender'     => 'user',
                'text'       => $conv->question,
                'created_at' => $conv->created_at?->format('Y-m-d H:i:s'),
            ];

            // Resposta gerada pela IA
            $timeline[] = [
                'id'           => $conv->id . '_ai',
                'sender'       => 'assistant',
                'text'         => $conv->response,
                'intent'       => $conv->intent,
                'provider'     => $conv->provider,
                'model'        => $conv->model,
                'tokens_total' => $conv->tokens_total,
                'cost_usd'     => (float) $conv->cost_usd,
                'context_data' => $conv->context_data,
                'created_at'   => $conv->created_at?->format('Y-m-d H:i:s'),
            ];
        }

        return response()->json([
            'status' => 'success',
            'data'   => $timeline,
        ]);
    }

    /**
     * Limpa o histórico de uma sessão.
     * DELETE /api/v1/chat/history
     */
    public function clearHistory(Request $request): JsonResponse
    {
        $sessionId = $request->query('session_id') ?: $request->input('session_id');

        if ($sessionId) {
            AiConversation::where('session_id', $sessionId)->delete();
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Histórico da sessão removido com sucesso.',
        ]);
    }
}