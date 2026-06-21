<?php

namespace Shearerline\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'shearerline_products';

    protected $fillable = [
        'name',
        'sku',
        'barcode',
        'supplier_id',
        'category',
        'unit',
        'sale_price',
        'weight',
        'description',
        'image_url',
        'stock',
        'warning_stock',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'weight' => 'decimal:2',
            'stock' => 'integer',
            'warning_stock' => 'integer',
            'status' => 'integer',
        ];
    }

    public function costs()
    {
        return $this->hasMany(ProductCost::class)->orderBy('effective_date', 'desc');
    }

    public function activeCosts()
    {
        return $this->hasMany(ProductCost::class)
            ->where('is_active', 1)
            ->whereDate('effective_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', now());
            })
            ->orderBy('effective_date', 'desc');
    }

    public function getCostAttribute()
    {
        return $this->activeCosts->sum('total_cost');
    }

    public function getTotalCostAttribute()
    {
        return $this->cost;
    }

    public function getCostBreakdownAttribute()
    {
        return $this->activeCosts
            ->groupBy('cost_type')
            ->map(function ($items, $type) {
                return [
                    'cost_type' => $type,
                    'cost_type_name' => get_cost_types()[$type] ?? $type,
                    'total' => $items->sum('total_cost'),
                    'items' => $items,
                ];
            })
            ->values();
    }

    public function getGrossMarginAttribute()
    {
        $price = (float) ($this->sale_price ?? 0);
        $cost = (float) ($this->cost ?? 0);
        if ($price <= 0) {
            return 0;
        }
        return round(($price - $cost) / $price, 4);
    }

    public function calculateCostAt(?string $date = null): array
    {
        $date = $date ?: now()->toDateString();

        $activeCosts = $this->costs()
            ->where('is_active', 1)
            ->whereDate('effective_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', $date);
            })
            ->get();

        $totalCost = 0;
        $breakdown = [];

        foreach ($activeCosts as $cost) {
            $totalCost += (float) $cost->total_cost;
            if (!isset($breakdown[$cost->cost_type])) {
                $breakdown[$cost->cost_type] = [
                    'cost_type' => $cost->cost_type,
                    'cost_type_name' => get_cost_types()[$cost->cost_type] ?? $cost->cost_type,
                    'total' => 0,
                    'items' => [],
                ];
            }
            $breakdown[$cost->cost_type]['total'] += (float) $cost->total_cost;
            $breakdown[$cost->cost_type]['items'][] = $cost;
        }

        return [
            'product_id' => $this->id,
            'product_name' => $this->name,
            'product_sku' => $this->sku,
            'sale_price' => (float) $this->sale_price,
            'total_cost' => round($totalCost, 2),
            'gross_margin' => $this->sale_price > 0 ? round(((float) $this->sale_price - $totalCost) / (float) $this->sale_price, 4) : 0,
            'breakdown' => array_values($breakdown),
            'calculation_date' => $date,
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeOfSupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeOfCategory($query, $category)
    {
        return $query->where('category', $category);
    }
}
