<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class AiConversation extends Model
{
    /**
     * Registro de auditoria de todas as interações com a IA.
     *
     * Imutável por design — conversas não devem ser editadas após registro.
     * Apenas created_at é relevante; updated_at é desabilitado.
     */

    const STATUS_SUCCESS  = 'success';
    const STATUS_ERROR    = 'error';
    const STATUS_TIMEOUT  = 'timeout';
    const STATUS_FALLBACK = 'fallback';
    const STATUS_CACHED   = 'cached';

    protected $fillable = [
        'session_id',
        'question',
        'context_data',
        'prompt_sent',
        'response',
        'intent',
        'provider',
        'model',
        'tokens_input',
        'tokens_output',
        'tokens_total',
        'cost_usd',
        'response_time_ms',
        'status',
        'error_message',
        'was_streamed',
    ];

    protected $casts = [
        'context_data'     => 'array',
        'tokens_input'     => 'integer',
        'tokens_output'    => 'integer',
        'tokens_total'     => 'integer',
        'cost_usd'         => 'decimal:6',
        'response_time_ms' => 'integer',
        'was_streamed'     => 'boolean',
    ];

    // ── Scopes ────────────────────────────────────────────────────────

    /** Histórico de uma sessão específica, em ordem cronológica. */
    public function scopeForSession(Builder $query, string $sessionId): Builder
    {
        return $query->where('session_id', $sessionId)
                     ->orderBy('created_at', 'asc');
    }

    /** Apenas chamadas com sucesso. */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    /** Apenas falhas (para monitoramento). */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_ERROR, self::STATUS_TIMEOUT]);
    }

    /** Filtrar por provedor. */
    public function scopeByProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    /** Custo total acumulado em um período. */
    public function scopeTotalCostInPeriod(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    // ── Accessors ─────────────────────────────────────────────────────

    /**
     * Custo formatado em USD com 6 casas decimais.
     */
    public function getFormattedCostAttribute(): string
    {
        return '$' . number_format((float) $this->cost_usd, 6);
    }
}