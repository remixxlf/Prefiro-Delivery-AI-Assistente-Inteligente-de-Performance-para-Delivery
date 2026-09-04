<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela de auditoria de conversas com a IA.
     *
     * Objetivos desta tabela:
     *  1. Rastreabilidade: registrar todas as interações pergunta/resposta
     *  2. Controle de custo: tokens consumidos e custo estimado por chamada
     *  3. Diagnóstico: detectar falhas, latências e respostas inválidas
     *  4. Análise de uso: perguntas mais frequentes, tokens por usuário
     *  5. Resposta a requisito do PDF (pergunta 8 do README obrigatório)
     *
     * Campos de custo seguem o formato de pricing da config/ai.php.
     *
     * Índices:
     *  - session_id  → recuperar histórico da sessão do usuário
     *  - created_at  → análise de uso por período
     *  - provider    → comparar custo entre provedores
     *  - status      → filtrar falhas / timeouts
     *  - Composto (session_id, created_at) → histórico cronológico por sessão
     */
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();

            // ── Identificação da sessão ────────────────────────────────
            $table->string('session_id', 100)
                ->comment('Identificador único da sessão do usuário (hash anônimo ou UUID)');

            // ── Pergunta do usuário ────────────────────────────────────
            $table->text('question')
                ->comment('Pergunta original enviada pelo usuário (sanitizada)');

            // ── Contexto enviado à IA ──────────────────────────────────
            $table->json('context_data')->nullable()
                ->comment('Dados do banco enviados como contexto para a IA (métricas agregadas, nunca dados brutos)');

            $table->text('prompt_sent')->nullable()
                ->comment('Prompt completo enviado ao modelo (para depuração e auditoria)');

            // ── Resposta da IA ─────────────────────────────────────────
            $table->text('response')->nullable()
                ->comment('Resposta retornada pelo modelo de IA');

            // ── Intenção detectada ─────────────────────────────────────
            $table->string('intent', 80)->nullable()
                ->comment('Intenção identificada pelo IntentResolver. Ex: revenue_drop, top_products, inactive_customers');

            // ── Provedor e modelo ──────────────────────────────────────
            $table->string('provider', 30)->default('openai')
                ->comment('Provedor de IA utilizado: openai | gemini | anthropic');

            $table->string('model', 80)->nullable()
                ->comment('Modelo exato utilizado. Ex: gpt-4o-mini, gemini-1.5-flash');

            // ── Controle de tokens ────────────────────────────────────
            $table->unsignedInteger('tokens_input')->default(0)
                ->comment('Tokens consumidos no prompt (input)');

            $table->unsignedInteger('tokens_output')->default(0)
                ->comment('Tokens consumidos na resposta (output)');

            $table->unsignedInteger('tokens_total')->default(0)
                ->comment('Total de tokens: tokens_input + tokens_output');

            // ── Controle de custo (USD) ───────────────────────────────
            $table->decimal('cost_usd', 10, 6)->default(0.000000)
                ->comment('Custo estimado da chamada em USD (baseado em pricing da config/ai.php)');

            // ── Performance ───────────────────────────────────────────
            $table->unsignedInteger('response_time_ms')->nullable()
                ->comment('Tempo de resposta da API de IA em milissegundos');

            // ── Status da chamada ──────────────────────────────────────
            $table->enum('status', [
                'success',   // resposta recebida com sucesso
                'error',     // erro na chamada à API da IA
                'timeout',   // timeout da requisição
                'fallback',  // respondeu via fallback (provedor alternativo)
                'cached',    // resposta servida do cache (sem chamada real)
            ])->default('success');

            $table->text('error_message')->nullable()
                ->comment('Mensagem de erro em caso de falha (status = error | timeout)');

            // ── Streaming ─────────────────────────────────────────────
            $table->boolean('was_streamed')->default(false)
                ->comment('Indica se a resposta foi transmitida via streaming (SSE)');

            $table->timestamps();

            // ── Índices simples ────────────────────────────────────────
            $table->index('session_id',  'idx_ai_conv_session_id');
            $table->index('created_at',  'idx_ai_conv_created_at');
            $table->index('provider',    'idx_ai_conv_provider');
            $table->index('status',      'idx_ai_conv_status');
            $table->index('intent',      'idx_ai_conv_intent');

            // ── Índices compostos ──────────────────────────────────────
            // Histórico cronológico por sessão (chat history)
            $table->index(['session_id', 'created_at'], 'idx_ai_conv_session_period');

            // Análise de custo por provedor e período
            $table->index(['provider', 'created_at'], 'idx_ai_conv_provider_period');

            // Filtrar apenas erros por período (monitoramento)
            $table->index(['status', 'created_at'], 'idx_ai_conv_status_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversations');
    }
};