<?php

namespace Shearerline\Database\Seeders;

use Shearerline\Models\Product;
use Shearerline\Models\Settlement;
use Shearerline\Services\SettlementService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SettlementSeeder extends Seeder
{
    protected $settlementService;

    public function __construct(SettlementService $settlementService)
    {
        $this->settlementService = $settlementService;
    }

    public function run(): void
    {
        $products = Product::take(5)->get();
        $statuses = [
            Settlement::STATUS_PENDING,
            Settlement::STATUS_CONFIRMED,
            Settlement::STATUS_SETTLED,
            Settlement::STATUS_CANCELLED,
            Settlement::STATUS_PENDING,
        ];

        $settlementCount = 0;
        $itemCount = 0;

        foreach ($statuses as $index => $status) {
            $settlementDate = now()->subDays(($index + 1) * 5);
            $itemProducts = $products->random(rand(2, 4));

            $items = [];
            foreach ($itemProducts as $product) {
                $quantity = rand(1, 5);
                $items[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'sale_price' => $product->sale_price,
                ];
            }

            $data = [
                'type' => [Settlement::TYPE_ORDER, Settlement::TYPE_MONTHLY, Settlement::TYPE_MANUAL][$index % 3],
                'settlement_date' => $settlementDate->toDateString(),
                'platform_fee' => rand(20, 100),
                'other_cost' => rand(10, 50),
                'supplier_ratio' => 0.50,
                'distributor_ratio' => 0.20,
                'platform_ratio' => 0.30,
                'items' => $items,
            ];

            $settlement = $this->settlementService->createSettlement($data);

            if ($status === Settlement::STATUS_CONFIRMED) {
                $this->settlementService->confirmSettlement($settlement->id);
            } elseif ($status === Settlement::STATUS_SETTLED) {
                $this->settlementService->confirmSettlement($settlement->id);
                $this->settlementService->settleSettlement($settlement->id);
            } elseif ($status === Settlement::STATUS_CANCELLED) {
                $this->settlementService->cancelSettlement($settlement->id);
            }

            $settlementCount++;
            $itemCount += count($items);
        }

        $this->command->info("SettlementSeeder: 已创建 {$settlementCount} 个结算单，共 {$itemCount} 条明细");
    }
}
