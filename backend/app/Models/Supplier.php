<?php

namespace App\Models;

use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'contact_person',
        'email',
        'phone',
        'tax_id',
        'address',
        'city',
        'state',
        'zip_code',
        'country',
        'payment_terms',
        'credit_limit',
        'current_balance',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'payment_terms' => 'integer',
        'credit_limit' => 'float',
        'current_balance' => 'float',
        'is_active' => 'boolean',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
