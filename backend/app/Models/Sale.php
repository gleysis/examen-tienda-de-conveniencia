<?php

namespace App\Models;

use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'user_id',
        'cash_register_id',
        'subtotal',
        'tax',
        'discount',
        'total',
        'payment_method',
        'status',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cashRegister()
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }
}
