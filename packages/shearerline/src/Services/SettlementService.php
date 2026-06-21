<?php

namespace Shearerline\Services;

use Shearerline\Models\Product;
use Shearerline\Models\Settlement;
use Shearerline\Models\SettlementItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SettlementService
{
    protected $costService;

    public function __construct(?CostCalculationService $costService = null)
    {
        $this->costService = $costService ?? new CostCalculationService();
    }

    public function createSettlement(array $data): Settlement
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['settlement_no'])) {
                $data['settlement_no'] = generate_settlement_no();
            }

            if (empty($data['type'])) {
                $data['type'] = Settlement::TYPE_MANUAL;
            }

            if (empty($data['settlement_date'])) {
                $data['settlement_date'] = now()->toDateString();
            }

            if (empty($data['status'])) {
                $data['status'] = Settlement::STATUS_PENDING;
            }

            if (!isset($data['supplier_ratio'])) {
                $data['supplier_ratio'] = config('shearerline.settlement.default_supplier_ratio', 0.5);
            }
            if (!isset($data['distributor_ratio'])) {
                $data['distributor_ratio'] = config('shearerline.settlement.default_distributor_ratio', 0.2);
            }
            if (!isset($data['platform_ratio'])) {
                $data['platform_ratio'] = config('shearerline.settlement.default_platform_ratio', 0.3);
            }

            if (Auth::check()) {
                $data['created_by'] = Auth::id();
                $data['updated_by'] = Auth::id();
            }

            $settlement = Settlement::create($data);

            if (!empty($data['items']) && is_array($data['items'])) {
                $this->attachItems($settlement, $data['items']);
                $settlement->refresh();
                $settlement->recalculateTotals()->save();
            }

            return $settlement->fresh('items');
        });
    }

    public function updateSettlement(int $id, array $data): Settlement
    {
        return DB::transaction(function () use ($id, $data) {
            $settlement = Settlement::findOrFail($id);

            if (!$settlement->isEditable()) {
                throw new \Shearerline\Exceptions\SettlementStateException('结算单当前状态不可编辑');
            }

            if (Auth::check()) {
                $data['updated_by'] = Auth::id();
            }

            $settlement->update($data);

            if (isset($data['items']) && is_array($data['items'])) {
                $settlement->items()->delete();
                $this->attachItems($settlement, $data['items']);
            }

            $settlement->refresh();
            $settlement->recalculateTotals()->save();

            return $settlement->fresh('items');
        });
    }

    protected function attachItems(Settlement $settlement, array $items): void
    {
        foreach ($items as $itemData) {
            if (empty($itemData['product_id'])) {
                continue;
            }

            $product = Product::find($itemData['product_id']);
            if (!$product) {
                continue;
            }

            $quantity = (int) ($itemData['quantity'] ?? 1);
            $salePrice = (float) ($itemData['sale_price'] ?? $product->sale_price);
            $unitCost = isset($itemData['unit_cost'])
                ? (float) $itemData['unit_cost']
                : $this->costService->calculateUnitCostForSettlement($product->id, $settlement->settlement_date);

            $settlement->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'quantity' => $quantity,
                'sale_price' => $salePrice,
                'total_sales' => round($salePrice * $quantity, 2),
                'unit_cost' => $unitCost,
                'total_cost' => round($unitCost * $quantity, 2),
                'profit' => round(($salePrice - $unitCost) * $quantity, 2),
            ]);
        }
    }

    public function confirmSettlement(int $id): Settlement
    {
        return DB::transaction(function () use ($id) {
            $settlement = Settlement::findOrFail($id);

            if (!$settlement->canConfirm()) {
                throw new \Shearerline\Exceptions\SettlementStateException('结算单当前状态不可确认');
            }

            if ($settlement->items->count() === 0) {
                throw new \Shearerline\Exceptions\SettlementStateException('结算单没有明细项，无法确认');
            }

            $settlement->recalculateTotals();

            $data = [
                'status' => Settlement::STATUS_CONFIRMED,
            ];

            if (Auth::check()) {
                $data['updated_by'] = Auth::id();
            }

            $settlement->update($data);

            return $settlement->fresh('items');
        });
    }

    public function settleSettlement(int $id): Settlement
    {
        return DB::transaction(function () use ($id) {
            $settlement = Settlement::findOrFail($id);

            if (!$settlement->canSettle()) {
                throw new \Shearerline\Exceptions\SettlementStateException('结算单当前状态不可结算');
            }

            $data = [
                'status' => Settlement::STATUS_SETTLED,
                'settled_at' => now(),
            ];

            if (Auth::check()) {
                $data['settled_by'] = Auth::id();
                $data['updated_by'] = Auth::id();
            }

            $settlement->update($data);

            return $settlement->fresh('items');
        });
    }

    public function cancelSettlement(int $id): Settlement
    {
        return DB::transaction(function () use ($id) {
            $settlement = Settlement::findOrFail($id);

            if (!$settlement->canCancel()) {
                throw new \Shearerline\Exceptions\SettlementStateException('结算单当前状态不可取消');
            }

            $data = [
                'status' => Settlement::STATUS_CANCELLED,
            ];

            if (Auth::check()) {
                $data['updated_by'] = Auth::id();
            }

            $settlement->update($data);

            return $settlement->fresh('items');
        });
    }

    public function calculateSettlement(array $items, array $ratios = []): array
    {
        $settlementDate = $ratios['settlement_date'] ?? now()->toDateString();
        $supplierRatio = (float) ($ratios['supplier_ratio'] ?? config('shearerline.settlement.default_supplier_ratio', 0.5));
        $distributorRatio = (float) ($ratios['distributor_ratio'] ?? config('shearerline.settlement.default_distributor_ratio', 0.2));
        $platformRatio = (float) ($ratios['platform_ratio'] ?? config('shearerline.settlement.default_platform_ratio', 0.3));
        $platformFee = (float) ($ratios['platform_fee'] ?? 0);
        $otherCost = (float) ($ratios['other_cost'] ?? 0);

        $calculatedItems = [];
        $orderCount = 0;
        $totalAmount = 0;
        $productCost = 0;

        foreach ($items as $itemData) {
            if (empty($itemData['product_id'])) {
                continue;
            }

            $product = Product::find($itemData['product_id']);
            if (!$product) {
                continue;
            }

            $quantity = (int) ($itemData['quantity'] ?? 1);
            $salePrice = (float) ($itemData['sale_price'] ?? $product->sale_price);
            $unitCost = isset($itemData['unit_cost'])
                ? (float) $itemData['unit_cost']
                : $this->costService->calculateUnitCostForSettlement($product->id, $settlementDate);

            $totalSales = round($salePrice * $quantity, 2);
            $totalCost = round($unitCost * $quantity, 2);
            $profit = round($totalSales - $totalCost, 2);

            $calculatedItems[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'quantity' => $quantity,
                'sale_price' => $salePrice,
                'total_sales' => $totalSales,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'profit' => $profit,
                'profit_rate' => $totalSales > 0 ? round($profit / $totalSales, 4) : 0,
            ];

            $orderCount++;
            $totalAmount += $totalSales;
            $productCost += $totalCost;
        }

        $totalCost = round($productCost + $platformFee + $otherCost, 2);
        $totalProfit = round($totalAmount - $totalCost, 2);
        $profitRate = $totalAmount > 0 ? round($totalProfit / $totalAmount, 4) : 0;

        $supplierShare = round($totalProfit * $supplierRatio, 2);
        $distributorShare = round($totalProfit * $distributorRatio, 2);
        $platformShare = round($totalProfit * $platformRatio, 2);

        return [
            'settlement_date' => $settlementDate,
            'items' => $calculatedItems,
            'summary' => [
                'order_count' => $orderCount,
                'total_amount' => $totalAmount,
                'product_cost' => round($productCost, 2),
                'platform_fee' => $platformFee,
                'other_cost' => $otherCost,
                'total_cost' => $totalCost,
                'total_profit' => $totalProfit,
                'profit_rate' => $profitRate,
            ],
            'shares' => [
                'supplier_ratio' => $supplierRatio,
                'distributor_ratio' => $distributorRatio,
                'platform_ratio' => $platformRatio,
                'supplier_share' => $supplierShare,
                'distributor_share' => $distributorShare,
                'platform_share' => $platformShare,
            ],
        ];
    }
}
