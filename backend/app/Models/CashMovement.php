<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashMovement extends Model
{
    public const TYPE_INCOME = 'income';

    public const TYPE_WITHDRAWAL = 'withdrawal';

    public const TYPE_EXPENSE = 'expense';

    protected $fillable = [
        'cash_register_id',
        'cash_register_session_id',
        'user_id',
        'type',
        'amount',
        'reason',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    public function cashRegister()
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function session()
    {
        return $this->belongsTo(CashRegisterSession::class, 'cash_register_session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether this movement adds cash to the drawer.
     */
    public function addsCash(): bool
    {
        return $this->type === self::TYPE_INCOME;
    }
}
