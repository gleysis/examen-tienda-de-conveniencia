<?php

namespace App\Models;

use App\Support\SystemSettings;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'sku',
        'description',
        'price',
        'cost',
        'stock',
        'min_stock',
        'max_stock',
        'expiry_date',
        'category_id',
        'supplier_id',
        'barcode',
        'image',
        'is_active',
    ];

    protected $casts = [
        'price' => 'float',
        'cost' => 'float',
        'stock' => 'integer',
        'min_stock' => 'integer',
        'max_stock' => 'integer',
        'expiry_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * Products in stock but at or below their minimum. A min_stock of 0 means
     * "no own minimum", in which case the configured default threshold applies.
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('stock', '>', 0)
            ->whereRaw('stock <= COALESCE(NULLIF(min_stock, 0), ?)', [self::defaultMinStock()]);
    }

    /**
     * Effective minimum stock for this product.
     */
    public function effectiveMinStock(): int
    {
        return $this->min_stock ?: self::defaultMinStock();
    }

    public static function defaultMinStock(): int
    {
        return (int) SystemSettings::value('low_stock_default', 5);
    }
}
