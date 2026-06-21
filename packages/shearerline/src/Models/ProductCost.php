<?php

namespace Shearerline\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCost extends Model
{
    use HasFactory;

    protected $table = 'shearerline_product_costs';

    const COST_TYPE_PURCHASE = 'purchase';
    const COST_TYPE_SHIPPING = 'shipping';
    const COST_TYPE_PACKAGING = 'packaging';
    const COST_TYPE_PLATFORM_FEE = 'platform_fee';
    const COST_TYPE_MARKETING = 'marketing';
    const COST_TYPE_TAX = 'tax';
    const COST_TYPE_OTHER = 'other';

    protected $fillable = [
        'product_id',
        'cost_type',
        'cost_name',
        'unit_cost',
        'quantity',
        'total_cost',
        'effective_date',
        'expiry_date',
        'is_active',
        'remark',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'quantity' => 'integer',
            'total_cost' => 'decimal:2',
            'effective_date' => 'date',
            'expiry_date' => 'date',
            'is_active' => 'integer',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getCostTypeNameAttribute()
    {
        $types = get_cost_types();
        return $types[$this->cost_type] ?? $this->cost_type;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', 0);
    }

    public function scopeEffectiveAt($query, $date = null)
    {
        $date = $date ?: now();
        return $query->whereDate('effective_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', $date);
            });
    }

    public function scopeOfProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeOfCostType($query, $costType)
    {
        return $query->where('cost_type', $costType);
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            $model->total_cost = round((float) ($model->unit_cost ?? 0) * (int) ($model->quantity ?? 1), 2);
        });
    }
}
