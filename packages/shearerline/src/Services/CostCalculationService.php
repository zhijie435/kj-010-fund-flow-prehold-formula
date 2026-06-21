<?php

namespace Shearerline\Services;

use Shearerline\Models\Product;

class CostCalculationService
{
    public function calculateProductCost(Product $product, ?string $date = null): array
    {
        $date = $date ?: now()->toDateString();

        $activeCosts = $product->costs()
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
            $costTotal = (float) $cost->total_cost;
            $totalCost += $costTotal;

            if (!isset($breakdown[$cost->cost_type])) {
                $breakdown[$cost->cost_type] = [
                    'cost_type' => $cost->cost_type,
                    'cost_type_name' => get_cost_types()[$cost->cost_type] ?? $cost->cost_type,
                    'total' => 0,
                    'items' => [],
                ];
            }
            $breakdown[$cost->cost_type]['total'] += $costTotal;
            $breakdown[$cost->cost_type]['items'][] = [
                'id' => $cost->id,
                'cost_name' => $cost->cost_name,
                'unit_cost' => (float) $cost->unit_cost,
                'quantity' => (int) $cost->quantity,
                'total_cost' => $costTotal,
                'effective_date' => $cost->effective_date ? \Illuminate\Support\Carbon::parse($cost->effective_date)->toDateString() : null,
                'expiry_date' => $cost->expiry_date ? \Illuminate\Support\Carbon::parse($cost->expiry_date)->toDateString() : null,
            ];
        }

        $salePrice = (float) $product->sale_price;
        $grossProfit = $salePrice - $totalCost;
        $grossMargin = $salePrice > 0 ? round($grossProfit / $salePrice, 4) : 0;

        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'sale_price' => $salePrice,
            'total_cost' => round($totalCost, 2),
            'gross_profit' => round($grossProfit, 2),
            'gross_margin' => $grossMargin,
            'breakdown' => array_values($breakdown),
            'calculation_date' => $date,
            'cost_item_count' => $activeCosts->count(),
        ];
    }

    public function calculateMultipleProductsCost(array $productIds, ?string $date = null): array
    {
        $date = $date ?: now()->toDateString();
        $products = Product::whereIn('id', $productIds)->get();

        $results = [];
        $grandTotal = [
            'product_count' => 0,
            'total_sale_price' => 0,
            'total_cost' => 0,
            'total_gross_profit' => 0,
            'type_breakdown' => [],
        ];

        foreach ($products as $product) {
            $result = $this->calculateProductCost($product, $date);
            $results[] = $result;

            $grandTotal['product_count']++;
            $grandTotal['total_sale_price'] += $result['sale_price'];
            $grandTotal['total_cost'] += $result['total_cost'];
            $grandTotal['total_gross_profit'] += $result['gross_profit'];

            foreach ($result['breakdown'] as $item) {
                $type = $item['cost_type'];
                if (!isset($grandTotal['type_breakdown'][$type])) {
                    $grandTotal['type_breakdown'][$type] = [
                        'cost_type' => $type,
                        'cost_type_name' => $item['cost_type_name'],
                        'total' => 0,
                    ];
                }
                $grandTotal['type_breakdown'][$type]['total'] += $item['total'];
            }
        }

        $grandTotal['total_sale_price'] = round($grandTotal['total_sale_price'], 2);
        $grandTotal['total_cost'] = round($grandTotal['total_cost'], 2);
        $grandTotal['total_gross_profit'] = round($grandTotal['total_gross_profit'], 2);
        $grandTotal['weighted_margin'] = $grandTotal['total_sale_price'] > 0
            ? round($grandTotal['total_gross_profit'] / $grandTotal['total_sale_price'], 4)
            : 0;
        $grandTotal['type_breakdown'] = array_values($grandTotal['type_breakdown']);
        $grandTotal['calculation_date'] = $date;

        return [
            'summary' => $grandTotal,
            'products' => $results,
        ];
    }

    public function calculateUnitCostForSettlement(int $productId, ?string $date = null): float
    {
        $product = Product::findOrFail($productId);
        $result = $this->calculateProductCost($product, $date);
        return $result['total_cost'];
    }

    public function calculateProductCostForSettlement(int $productId, ?string $date = null): array
    {
        $product = Product::findOrFail($productId);
        $result = $this->calculateProductCost($product, $date);
        return [
            'unit_cost' => $result['total_cost'],
            'cost_breakdown' => $result['breakdown'],
            'cost_item_count' => $result['cost_item_count'],
        ];
    }

    public function calculateProductCostByGrade(Product $product, string $grade = null): array
    {
        $grade = $grade ?: get_default_grade();
        $discountRate = get_grade_discount_rate($grade);
        $gradeInfo = get_grade_discounts()[$grade] ?? ['name' => '未知等级', 'discount_rate' => 0];

        $supplierPrice = (float) $product->supplier_price;
        $unitCost = round($supplierPrice * (1 - $discountRate), 2);

        $salePrice = (float) $product->sale_price;
        $grossProfit = $salePrice - $unitCost;
        $grossMargin = $salePrice > 0 ? round($grossProfit / $salePrice, 4) : 0;

        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'sale_price' => $salePrice,
            'supplier_price' => $supplierPrice,
            'grade' => $grade,
            'grade_name' => $gradeInfo['name'],
            'discount_rate' => $discountRate,
            'unit_cost' => $unitCost,
            'gross_profit' => round($grossProfit, 2),
            'gross_margin' => $grossMargin,
            'calculation_method' => 'grade_discount',
        ];
    }

    public function calculateMultipleProductsCostByGrade(array $productIds, string $grade = null): array
    {
        $grade = $grade ?: get_default_grade();
        $products = Product::whereIn('id', $productIds)->get();

        $results = [];
        $grandTotal = [
            'product_count' => 0,
            'total_sale_price' => 0,
            'total_supplier_price' => 0,
            'total_cost' => 0,
            'total_gross_profit' => 0,
            'grade' => $grade,
            'grade_name' => get_grade_discounts()[$grade]['name'] ?? '未知等级',
            'discount_rate' => get_grade_discount_rate($grade),
        ];

        foreach ($products as $product) {
            $result = $this->calculateProductCostByGrade($product, $grade);
            $results[] = $result;

            $grandTotal['product_count']++;
            $grandTotal['total_sale_price'] += $result['sale_price'];
            $grandTotal['total_supplier_price'] += $result['supplier_price'];
            $grandTotal['total_cost'] += $result['unit_cost'];
            $grandTotal['total_gross_profit'] += $result['gross_profit'];
        }

        $grandTotal['total_sale_price'] = round($grandTotal['total_sale_price'], 2);
        $grandTotal['total_supplier_price'] = round($grandTotal['total_supplier_price'], 2);
        $grandTotal['total_cost'] = round($grandTotal['total_cost'], 2);
        $grandTotal['total_gross_profit'] = round($grandTotal['total_gross_profit'], 2);
        $grandTotal['weighted_margin'] = $grandTotal['total_sale_price'] > 0
            ? round($grandTotal['total_gross_profit'] / $grandTotal['total_sale_price'], 4)
            : 0;

        return [
            'summary' => $grandTotal,
            'products' => $results,
        ];
    }

    public function calculateProductCostWithQuantity(
        Product $product,
        int $quantity = 1,
        string $grade = null
    ): array {
        $grade = $grade ?: get_default_grade();
        $baseResult = $this->calculateProductCostByGrade($product, $grade);

        $totalCost = round($baseResult['unit_cost'] * $quantity, 2);
        $totalSales = round($baseResult['sale_price'] * $quantity, 2);
        $totalProfit = round($totalSales - $totalCost, 2);
        $profitRate = $totalSales > 0 ? round($totalProfit / $totalSales, 4) : 0;

        return [
            ...$baseResult,
            'quantity' => $quantity,
            'total_cost' => $totalCost,
            'total_sales' => $totalSales,
            'total_profit' => $totalProfit,
            'profit_rate' => $profitRate,
        ];
    }

    public function calculateIncreasedProductCost(array $items, string $grade = null): array
    {
        $grade = $grade ?: get_default_grade();
        $gradeInfo = get_grade_discounts()[$grade] ?? ['name' => '未知等级', 'discount_rate' => 0];
        $discountRate = (float) $gradeInfo['discount_rate'];

        $calculatedItems = [];
        $totalIncreasedCost = 0;
        $totalQuantity = 0;

        foreach ($items as $itemData) {
            if (empty($itemData['product_id'])) {
                continue;
            }

            $product = Product::find($itemData['product_id']);
            if (!$product) {
                continue;
            }

            $quantity = (int) ($itemData['quantity'] ?? 1);
            $supplierPrice = isset($itemData['supplier_price'])
                ? (float) $itemData['supplier_price']
                : (float) $product->supplier_price;

            $unitCost = round($supplierPrice * (1 - $discountRate), 2);
            $totalCost = round($unitCost * $quantity, 2);

            $calculatedItems[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'supplier_price' => $supplierPrice,
                'discount_rate' => $discountRate,
                'unit_cost' => $unitCost,
                'quantity' => $quantity,
                'total_cost' => $totalCost,
            ];

            $totalIncreasedCost += $totalCost;
            $totalQuantity += $quantity;
        }

        return [
            'grade' => $grade,
            'grade_name' => $gradeInfo['name'],
            'discount_rate' => $discountRate,
            'items' => $calculatedItems,
            'total_quantity' => $totalQuantity,
            'total_increased_cost' => round($totalIncreasedCost, 2),
            'formula' => '增加商品成本 = Σ(供货价 × (1 - 等级折扣率) × 数量)',
        ];
    }
}
