<?php

namespace App\Models;

use Database\Factories\CashRegisterFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CashRegister extends Model
{
    /** @use HasFactory<CashRegisterFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'location',
        'user_id',
        'status',
        'notes',
    ];

    /**
     * The cashier this drawer is permanently assigned to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CashRegisterSession::class);
    }

    public function openSession(): HasOne
    {
        return $this->hasOne(CashRegisterSession::class)->where('status', 'open');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', 'closed');
    }

    /**
     * Opens a new session for the given responsible cashier. A drawer with
     * no owner yet becomes assigned to that cashier.
     */
    public function openSessionFor(User $responsible, User $openedBy, float $openingBalance): CashRegisterSession
    {
        $session = $this->sessions()->create([
            'user_id' => $responsible->id,
            'opened_by' => $openedBy->id,
            'opening_balance' => $openingBalance,
            'expected_closing' => $openingBalance,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $this->update([
            'status' => 'open',
            'user_id' => $this->user_id ?? $responsible->id,
        ]);

        return $session;
    }
}
