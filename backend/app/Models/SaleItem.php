<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'unit_price',
        'discount',
        'subtotal',
    ];

    protected $casts = [
        'unit_price' => 'float',
        'discount' => 'float',
        'subtotal' => 'float',
        'quantity' => 'integer',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(ReturnRefund::class);
    }

    /**
     * Units not yet covered by a pending, approved or processed return.
     */
    public function returnableQuantity(): int
    {
        return $this->quantity - (int) $this->returns()->where('status', '!=', 'rejected')->sum('quantity');
    }

    /**
     * Amount the customer actually paid for the given units: the line subtotal
     * prorated by the sale's discount and tax.
     */
    public function paidAmountFor(int $quantity): float
    {
        $sale = $this->sale;
        $saleRatio = $sale->subtotal > 0 ? $sale->total / $sale->subtotal : 0;

        return round($this->subtotal / $this->quantity * $quantity * $saleRatio, 2);
    }
}
