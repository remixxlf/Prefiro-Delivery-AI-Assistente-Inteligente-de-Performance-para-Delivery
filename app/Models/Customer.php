<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'first_order_at',
        'last_order_at',
    ];

    protected $casts = [
        'first_order_at' => 'datetime',
        'last_order_at'  => 'datetime',
    ];

    // ── Relacionamentos ────────────────────────────────────────────────
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────

    /**
     * Clientes que não compraram há mais de X dias.
     */
    public function scopeInactiveSince(Builder $query, int $days = 30): Builder
    {
        return $query->where('last_order_at', '<', now()->subDays($days))
                     ->orWhereNull('last_order_at');
    }

    /**
     * Clientes com pedidos no período informado.
     */
    public function scopeActiveInPeriod(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('last_order_at', [$from, $to]);
    }

    /**
     * Novos clientes em um período (primeiro pedido nesse intervalo).
     */
    public function scopeNewInPeriod(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('first_order_at', [$from, $to]);
    }
}