<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashRegister extends Model
{
    protected $fillable = [
        'name',
        'location',
        'user_id',
        'opening_balance',
        'current_balance',
        'cash_sales',
        'card_sales',
        'transfer_sales',
        'total_sales',
        'expected_closing',
        'actual_closing',
        'difference',
        'status',
        'opened_at',
        'closed_at',
        'notes',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'cash_sales' => 'decimal:2',
        'card_sales' => 'decimal:2',
        'transfer_sales' => 'decimal:2',
        'total_sales' => 'decimal:2',
        'expected_closing' => 'decimal:2',
        'actual_closing' => 'decimal:2',
        'difference' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function open(float $openingBalance, int $userId): void
    {
        $this->update([
            'status' => 'open',
            'opening_balance' => $openingBalance,
            'current_balance' => $openingBalance,
            'user_id' => $userId,
            'opened_at' => now(),
            'cash_sales' => 0,
            'card_sales' => 0,
            'transfer_sales' => 0,
            'total_sales' => 0,
        ]);
    }

    public function close(float $actualClosing): void
    {
        $difference = $actualClosing - $this->expected_closing;

        $this->update([
            'status' => 'closed',
            'actual_closing' => $actualClosing,
            'difference' => $difference,
            'closed_at' => now(),
        ]);
    }

    public function addSale(float $amount, string $paymentMethod): void
    {
        $this->increment('total_sales', $amount);
        $this->increment('current_balance', $amount);

        switch ($paymentMethod) {
            case 'cash':
                $this->increment('cash_sales', $amount);
                break;
            case 'card':
                $this->increment('card_sales', $amount);
                break;
            case 'transfer':
                $this->increment('transfer_sales', $amount);
                break;
        }

        $this->update(['expected_closing' => $this->opening_balance + $this->cash_sales]);
    }
}
