<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnRefund extends Model
{
    protected $fillable = [
        'return_number',
        'sale_id',
        'sale_item_id',
        'product_id',
        'user_id',
        'quantity',
        'refund_amount',
        'reason',
        'reason_description',
        'status',
        'refund_method',
        'reference_number',
        'processed_date',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'refund_amount' => 'float',
        'processed_date' => 'date',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function saleItem()
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }
}
