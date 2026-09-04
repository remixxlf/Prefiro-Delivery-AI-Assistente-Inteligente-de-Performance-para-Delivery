<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class Order extends Model
{
    use HasFactory;

    /**
     * Status válidos para um pedido.
     * Usando constants para evitar strings mágicas no código.
     */
    const STATUS_PENDING          = 'pending';
    const STATUS_CONFIRMED        = 'confirmed';
    const STATUS_PREPARING        = 'preparing';
    const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
    const STATUS_DELIVERED        = 'delivered';
    const STATUS_CANCELLED        = 'cancelled';

    protected $fillable = [
        'customer_id',
        'ordered_at',
        'status',
        'discount',
        'delivery_fee',
        'total',
        'payment_method',
        'notes',
    ];

    protected $casts = [
        'ordered_at'   => 'datetime',
        'discount'     => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total'        => 'decimal:2',
    ];

    // ── Relacionamentos ────────────────────────────────────────────────
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────

    /** Apenas pedidos entregues (base dos relatórios financeiros). */
    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DELIVERED);
    }

    /** Excluir cancelados das análises. */
    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_CANCELLED);
    }

    /** Filtrar por período. */
    public function scopeInPeriod(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('ordered_at', [$from, $to]);
    }

    /** Pedidos do mês corrente. */
    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereYear('ordered_at', now()->year)
                     ->whereMonth('ordered_at', now()->month);
    }

    /** Pedidos do mês anterior. */
    public function scopeLastMonth(Builder $query): Builder
    {
        $lastMonth = now()->subMonth();
        return $query->whereYear('ordered_at', $lastMonth->year)
                     ->whereMonth('ordered_at', $lastMonth->month);
    }

    /** Pedidos de um cliente específico. */
    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }
}