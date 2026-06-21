<?php

namespace Shearerline\Database\Seeders;

use Shearerline\Models\Product;
use Shearerline\Models\ProductCost;
use Illuminate\Database\Seeder;

class ProductCostSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::all();
        $costCount = 0;

        $costTypes = [
            'purchase' => ['name' => '采购成本', 'ratio' => 0.6],
            'shipping' => ['name' => '物流成本', 'ratio' => 0.08],
            'packaging' => ['name' => '包装成本', 'ratio' => 0.03],
            'platform_fee' => ['name' => '平台费用', 'ratio' => 0.1],
            'marketing' => ['name' => '营销成本', 'ratio' => 0.12],
            'tax' => ['name' => '税费', 'ratio' => 0.05],
            'other' => ['name' => '其他成本', 'ratio' => 0.02],
        ];

        foreach ($products as $product) {
            $baseCost = $product->supplier_price;

            foreach ($costTypes as $type => $config) {
                $unitCost = round($baseCost * $config['ratio'], 2);
                $totalCost = round($unitCost * 1, 2);

                ProductCost::create([
                    'product_id' => $product->id,
                    'cost_type' => $type,
                    'cost_name' => $config['name'],
                    'unit_cost' => $unitCost,
                    'quantity' => 1,
                    'total_cost' => $totalCost,
                    'effective_date' => now()->subMonths(3)->toDateString(),
                    'expiry_date' => null,
                    'is_active' => 1,
                ]);

                $costCount++;
            }
        }

        $this->command->info("ProductCostSeeder: 已创建 {$costCount} 条成本记录");
    }
}
