<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'value',
        'minimum_purchase',
        'maximum_discount',
        'usage_limit',
        'times_used',
        'buy_quantity',
        'get_quantity',
        'start_date',
        'end_date',
        'is_active',
        'applicable_to',
        'description',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'minimum_purchase' => 'decimal:2',
        'maximum_discount' => 'decimal:2',
        'usage_limit' => 'integer',
        'times_used' => 'integer',
        'buy_quantity' => 'integer',
        'get_quantity' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            });
    }

    public function scopeValidForPurchase($query, $purchaseAmount)
    {
        return $query->active()
            ->where(function ($q) use ($purchaseAmount) {
                $q->whereNull('minimum_purchase')
                    ->orWhere('minimum_purchase', '<=', $purchaseAmount);
            });
    }

    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->usage_limit && $this->times_used >= $this->usage_limit) {
            return false;
        }

        if ($this->start_date && $this->start_date > now()) {
            return false;
        }

        if ($this->end_date && $this->end_date < now()) {
            return false;
        }

        return true;
    }

    public function incrementUsage(): void
    {
        $this->increment('times_used');
    }
}
