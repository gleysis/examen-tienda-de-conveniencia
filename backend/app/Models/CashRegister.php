<?php

namespace App\Models;

use Database\Factories\CashRegisterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashRegister extends Model
{
    /** @use HasFactory<CashRegisterFactory> */
    use HasFactory;

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
        'opening_balance' => 'float',
        'current_balance' => 'float',
        'cash_sales' => 'float',
        'card_sales' => 'float',
        'transfer_sales' => 'float',
        'total_sales' => 'float',
        'expected_closing' => 'float',
        'actual_closing' => 'float',
        'difference' => 'float',
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

    public function movements()
    {
        return $this->hasMany(CashMovement::class);
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

        switch ($paymentMethod) {
            case 'cash':
                $this->increment('cash_sales', $amount);
                $this->increment('current_balance', $amount);
                break;
            case 'card':
                $this->increment('card_sales', $amount);
                break;
            case 'transfer':
                $this->increment('transfer_sales', $amount);
                break;
        }

        $this->update(['expected_closing' => $this->computedExpectedClosing()]);
    }

    public function addMovement(string $type, float $amount): void
    {
        $delta = $type === CashMovement::TYPE_INCOME ? $amount : -$amount;
        $this->increment('current_balance', $delta);
        $this->update(['expected_closing' => $this->computedExpectedClosing()]);
    }

    public function computedExpectedClosing(): float
    {
        $cashIn = (float) $this->movements()->where('type', CashMovement::TYPE_INCOME)->sum('amount');
        $cashOut = (float) $this->movements()->whereIn('type', [CashMovement::TYPE_WITHDRAWAL, CashMovement::TYPE_EXPENSE])->sum('amount');

        return $this->opening_balance + $this->cash_sales + $cashIn - $cashOut;
    }
}
