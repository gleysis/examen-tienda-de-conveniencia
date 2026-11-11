<?php

namespace App\Models;

use Database\Factories\CashCountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashCount extends Model
{
    /** @use HasFactory<CashCountFactory> */
    use HasFactory;

    public const TYPE_CLOSING = 'closing';

    protected $fillable = [
        'cash_register_session_id',
        'user_id',
        'expected_amount',
        'counted_amount',
        'difference',
        'type',
        'notes',
    ];

    protected $casts = [
        'expected_amount' => 'float',
        'counted_amount' => 'float',
        'difference' => 'float',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class, 'cash_register_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
