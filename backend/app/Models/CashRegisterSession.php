<?php

namespace App\Models;

use Database\Factories\CashRegisterSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CashRegisterSession extends Model
{
    /** @use HasFactory<CashRegisterSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'cash_register_id',
        'user_id',
        'opened_by',
        'closed_by',
        'opening_balance',
        'cash_sales',
        'card_sales',
        'transfer_sales',
        'total_sales',
        'expected_closing',
        'status',
        'opened_at',
        'closed_at',
        'notes',
    ];

    protected $casts = [
        'opening_balance' => 'float',
        'cash_sales' => 'float',
        'card_sales' => 'float',
        'transfer_sales' => 'float',
        'total_sales' => 'float',
        'expected_closing' => 'float',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    /**
     * The cashier responsible for the drawer during this session.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function cashCount(): HasOne
    {
        return $this->hasOne(CashCount::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', 'closed');
    }

    public function addSale(float $amount, string $paymentMethod): void
    {
        $columns = match ($paymentMethod) {
            'cash' => ['cash_sales', 'expected_closing'],
            'card' => ['card_sales'],
            'transfer' => ['transfer_sales'],
            default => [],
        };

        $this->incrementEach(array_fill_keys(['total_sales', ...$columns], $amount));
    }

    public function addMovement(string $type, float $amount): void
    {
        $delta = $type === CashMovement::TYPE_INCOME ? $amount : -$amount;
        $this->increment('expected_closing', $delta);
    }

    /**
     * Records the closing arqueo and marks the session and register closed.
     * Must run inside a transaction; the row is re-locked and its state
     * re-checked so concurrent closes cannot produce two cash counts.
     */
    public function close(float $countedAmount, int $closedById): CashCount
    {
        $session = self::whereKey($this->id)->lockForUpdate()->firstOrFail();

        abort_if($session->status !== 'open', 422, 'Esta caja registradora ya está cerrada');

        $count = $session->cashCount()->create([
            'user_id' => $closedById,
            'expected_amount' => $session->expected_closing,
            'counted_amount' => $countedAmount,
            'difference' => round($countedAmount - $session->expected_closing, 2),
            'type' => CashCount::TYPE_CLOSING,
        ]);

        $session->update([
            'status' => 'closed',
            'closed_by' => $closedById,
            'closed_at' => now(),
        ]);

        $session->cashRegister()->update(['status' => 'closed']);

        return $count;
    }
}
