<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderItem extends Model
{
    use HasFactory;

    /**
     * order_items não tem timestamps (não há updated_at relevante
     * — itens não são editados após a criação do pedido).
     */
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    protected $casts = [
        'quantity'   => 'integer',
        'unit_price' => 'decimal:2',
        'subtotal'   => 'decimal:2',
    ];

    // ── Relacionamentos ────────────────────────────────────────────────
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /**
     * Recalcula o subtotal com base em quantity × unit_price.
     * Chamado antes de salvar para garantir consistência.
     */
    protected static function booted(): void
    {
        static::creating(function (OrderItem $item) {
            $item->subtotal = $item->quantity * $item->unit_price;
        });
    }
}