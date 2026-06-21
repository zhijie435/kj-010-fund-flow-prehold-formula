<?php

namespace Shearerline\Services;

use Shearerline\Models\Product;
use Shearerline\Models\Settlement;
use Shearerline\Models\SettlementItem;
use Shearerline\StateMachines\SettlementStateMachine;
use Shearerline\Exceptions\SettlementStateException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;

class SettlementService
{
    protected $costService;
    protected $fundFlowService;

    public function __construct(
        ?CostCalculationService $costService = null,
        ?FundFlowService $fundFlowService = null
    ) {
        $this->costService = $costService ?? new CostCalculationService();
        $this->fundFlowService = $fundFlowService ?? new FundFlowService();
    }

    public function createSettlement(array $data): Settlement
    {
        return DB::transaction(function () use ($data) {
            $data = $this->prepareSettlementData($data);

            $settlement = Settlement::create($data);

            if (!empty($data['items']) && is_array($data['items'])) {
                $this->attachItems($settlement, $data['items']);
                $settlement->refresh();
            }

            $settlement->recalculateTotals()->save();

            return $settlement->fresh('items');
        });
    }

    public function updateSettlement(int $id, array $data): Settlement
    {
        return DB::transaction(function () use ($id, $data) {
            $settlement = Settlement::findOrFail($id);

            $stateMachine = new SettlementStateMachine($settlement);
            if (!$stateMachine->isEditable()) {
                throw new SettlementStateException('结算单当前状态不可编辑');
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

    protected function prepareSettlementData(array $data): array
    {
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

        return $data;
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
            $costBreakdown = null;

            if (isset($itemData['unit_cost'])) {
                $unitCost = (float) $itemData['unit_cost'];
            } else {
                $costInfo = $this->costService->calculateProductCostForSettlement($product->id, $settlement->settlement_date);
                $unitCost = $costInfo['unit_cost'];
                $costBreakdown = $costInfo['cost_breakdown'];
            }

            $settlement->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'quantity' => $quantity,
                'sale_price' => $salePrice,
                'total_sales' => round($salePrice * $quantity, 2),
                'unit_cost' => $unitCost,
                'total_cost' => round($unitCost * $quantity, 2),
                'cost_breakdown' => $costBreakdown,
                'profit' => round(($salePrice - $unitCost) * $quantity, 2),
            ]);
        }
    }

    public function confirmSettlement(int $id): Settlement
    {
        return DB::transaction(function () use ($id) {
            $settlement = Settlement::with('items')->findOrFail($id);
            $stateMachine = new SettlementStateMachine($settlement);
            return $stateMachine->transition('confirm');
        });
    }

    public function settleSettlement(int $id): Settlement
    {
        return DB::transaction(function () use ($id) {
            $settlement = Settlement::with('items')->findOrFail($id);
            $stateMachine = new SettlementStateMachine($settlement);
            return $stateMachine->transition('settle');
        });
    }

    public function cancelSettlement(int $id): Settlement
    {
        return DB::transaction(function () use ($id) {
            $settlement = Settlement::with('items')->findOrFail($id);
            $stateMachine = new SettlementStateMachine($settlement);
            return $stateMachine->transition('cancel');
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
        $productCostBreakdown = [];

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
            $costBreakdown = null;

            if (isset($itemData['unit_cost'])) {
                $unitCost = (float) $itemData['unit_cost'];
            } else {
                $costInfo = $this->costService->calculateProductCostForSettlement($product->id, $settlementDate);
                $unitCost = $costInfo['unit_cost'];
                $costBreakdown = $costInfo['cost_breakdown'];
            }

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
                'cost_breakdown' => $costBreakdown,
                'profit' => $profit,
                'profit_rate' => $totalSales > 0 ? round($profit / $totalSales, 4) : 0,
            ];

            $orderCount++;
            $totalAmount += $totalSales;
            $productCost += $totalCost;

            if ($costBreakdown) {
                foreach ($costBreakdown as $bd) {
                    $type = $bd['cost_type'];
                    if (!isset($productCostBreakdown[$type])) {
                        $productCostBreakdown[$type] = [
                            'cost_type' => $type,
                            'cost_type_name' => $bd['cost_type_name'],
                            'total' => 0,
                        ];
                    }
                    $productCostBreakdown[$type]['total'] += $bd['total'] * $quantity;
                }
            }
        }

        foreach ($productCostBreakdown as &$bd) {
            $bd['total'] = round($bd['total'], 2);
        }
        $productCostBreakdown = array_values($productCostBreakdown);

        $totalCost = round($productCost + $platformFee + $otherCost, 2);
        $totalProfit = round($totalAmount - $totalCost, 2);
        $profitRate = $totalAmount > 0 ? round($totalProfit / $totalAmount, 4) : 0;

        $supplierShare = round($totalProfit * $supplierRatio, 2);
        $distributorShare = round($totalProfit * $distributorRatio, 2);
        $platformShare = round($totalProfit * $platformRatio, 2);

        $itemCollection = new Collection(
            array_map(fn($item) => new SettlementItem($item), $calculatedItems)
        );

        $fundFlow = $this->fundFlowService->buildFundFlow(
            $totalAmount,
            $productCost,
            $platformFee,
            $otherCost,
            $totalCost,
            $totalProfit,
            $supplierShare,
            $distributorShare,
            $platformShare,
            $supplierRatio,
            $distributorRatio,
            $platformRatio
        );

        $withholdFormula = $this->fundFlowService->buildWithholdFormula(
            $itemCollection,
            $totalAmount,
            $productCost,
            $platformFee,
            $otherCost,
            $totalCost,
            $totalProfit,
            $profitRate,
            $supplierShare,
            $distributorShare,
            $platformShare,
            $supplierRatio,
            $distributorRatio,
            $platformRatio
        );

        return [
            'settlement_date' => $settlementDate,
            'items' => $calculatedItems,
            'summary' => [
                'order_count' => $orderCount,
                'total_amount' => $totalAmount,
                'product_cost' => round($productCost, 2),
                'product_cost_breakdown' => $productCostBreakdown,
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
            'fund_flow' => $fundFlow,
            'withhold_formula' => $withholdFormula,
        ];
    }
}
