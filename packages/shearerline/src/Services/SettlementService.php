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

            $settlement->fill($data);
            $settlement->save();

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

            $settlement->recalculateTotals();

            $data = [
                'status' => Settlement::STATUS_SETTLED,
                'settled_at' => now(),
            ];

            if (Auth::check()) {
                $data['settled_by'] = Auth::id();
                $data['updated_by'] = Auth::id();
            }

            $settlement->fill($data);
            $settlement->save();

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

        $fundFlowNodes = [
            [
                'id' => 'customer',
                'name' => '客户',
                'amount' => $totalAmount,
                'type' => 'source',
                'description' => '销售收款',
            ],
            [
                'id' => 'platform',
                'name' => '平台账户',
                'amount' => $totalAmount,
                'type' => 'transfer',
                'description' => '资金归集',
            ],
            [
                'id' => 'product_cost',
                'name' => '商品成本',
                'amount' => round($productCost, 2),
                'type' => 'cost',
                'description' => '供应商货款',
            ],
            [
                'id' => 'platform_fee',
                'name' => '平台费用',
                'amount' => $platformFee,
                'type' => 'cost',
                'description' => '平台服务费预扣',
            ],
            [
                'id' => 'other_cost',
                'name' => '其他成本',
                'amount' => $otherCost,
                'type' => 'cost',
                'description' => '其他杂费',
            ],
            [
                'id' => 'profit',
                'name' => '可分配利润',
                'amount' => $totalProfit,
                'type' => 'profit',
                'description' => '利润总额',
            ],
            [
                'id' => 'supplier',
                'name' => '供应商',
                'amount' => $supplierShare,
                'type' => 'recipient',
                'description' => '利润分成',
            ],
            [
                'id' => 'distributor',
                'name' => '分销商',
                'amount' => $distributorShare,
                'type' => 'recipient',
                'description' => '利润分成',
            ],
            [
                'id' => 'platform_income',
                'name' => '平台收益',
                'amount' => $platformShare,
                'type' => 'recipient',
                'description' => '利润分成',
            ],
        ];

        $fundFlowEdges = [
            ['from' => 'customer', 'to' => 'platform', 'amount' => $totalAmount, 'label' => '销售总额'],
            ['from' => 'platform', 'to' => 'product_cost', 'amount' => round($productCost, 2), 'label' => '商品成本'],
            ['from' => 'platform', 'to' => 'platform_fee', 'amount' => $platformFee, 'label' => '平台费用'],
            ['from' => 'platform', 'to' => 'other_cost', 'amount' => $otherCost, 'label' => '其他成本'],
            ['from' => 'platform', 'to' => 'profit', 'amount' => $totalProfit, 'label' => '剩余利润'],
            ['from' => 'profit', 'to' => 'supplier', 'amount' => $supplierShare, 'label' => ($supplierRatio * 100) . '%'],
            ['from' => 'profit', 'to' => 'distributor', 'amount' => $distributorShare, 'label' => ($distributorRatio * 100) . '%'],
            ['from' => 'profit', 'to' => 'platform_income', 'amount' => $platformShare, 'label' => ($platformRatio * 100) . '%'],
        ];

        $fundFlowDescription = sprintf(
            '资金流向：客户支付 ¥%s → 平台归集后，扣除商品成本 ¥%s、平台费用 ¥%s、其他成本 ¥%s，剩余利润 ¥%s 按比例分配：供应商 %s%% (¥%s)、分销商 %s%% (¥%s)、平台 %s%% (¥%s)。',
            format_money($totalAmount),
            format_money($productCost),
            format_money($platformFee),
            format_money($otherCost),
            format_money($totalProfit),
            $supplierRatio * 100,
            format_money($supplierShare),
            $distributorRatio * 100,
            format_money($distributorShare),
            $platformRatio * 100,
            format_money($platformShare)
        );

        $withholdFormulas = [
            [
                'name' => '销售总额',
                'formula' => '销售总额 = Σ(商品单价 × 数量)',
                'value' => $totalAmount,
                'calculation' => collect($calculatedItems)->map(function($item) {
                    return "¥{$item['sale_price']} × {$item['quantity']}";
                })->implode(' + '),
            ],
            [
                'name' => '商品成本',
                'formula' => '商品成本 = Σ(单位成本 × 数量)',
                'value' => round($productCost, 2),
                'calculation' => collect($calculatedItems)->map(function($item) {
                    return "¥{$item['unit_cost']} × {$item['quantity']}";
                })->implode(' + '),
            ],
            [
                'name' => '预扣费用合计',
                'formula' => '预扣费用 = 平台费用 + 其他成本',
                'value' => round($platformFee + $otherCost, 2),
                'calculation' => "¥{$platformFee} + ¥{$otherCost}",
            ],
            [
                'name' => '总成本',
                'formula' => '总成本 = 商品成本 + 平台费用 + 其他成本',
                'value' => $totalCost,
                'calculation' => "¥" . format_money($productCost) . " + ¥{$platformFee} + ¥{$otherCost}",
            ],
            [
                'name' => '利润总额',
                'formula' => '利润总额 = 销售总额 - 总成本',
                'value' => $totalProfit,
                'calculation' => "¥{$totalAmount} - ¥{$totalCost}",
            ],
            [
                'name' => '利润率',
                'formula' => '利润率 = 利润总额 ÷ 销售总额 × 100%',
                'value' => $profitRate,
                'calculation' => $totalAmount > 0 ? "¥{$totalProfit} ÷ ¥{$totalAmount} × 100%" : 'N/A',
                'is_percent' => true,
            ],
            [
                'name' => '供应商分成',
                'formula' => '供应商分成 = 利润总额 × 供应商分成比例',
                'value' => $supplierShare,
                'calculation' => "¥{$totalProfit} × " . ($supplierRatio * 100) . "%",
            ],
            [
                'name' => '分销商分成',
                'formula' => '分销商分成 = 利润总额 × 分销商分成比例',
                'value' => $distributorShare,
                'calculation' => "¥{$totalProfit} × " . ($distributorRatio * 100) . "%",
            ],
            [
                'name' => '平台分成',
                'formula' => '平台分成 = 利润总额 × 平台分成比例',
                'value' => $platformShare,
                'calculation' => "¥{$totalProfit} × " . ($platformRatio * 100) . "%",
            ],
        ];

        $withholdSummary = sprintf(
            '本次结算共 %d 件商品，销售总额 ¥%s，扣除各项成本 ¥%s 后，实现利润 ¥%s，利润率 %s%%。',
            $orderCount,
            format_money($totalAmount),
            format_money($totalCost),
            format_money($totalProfit),
            $totalAmount > 0 ? round($profitRate * 100, 2) : '0'
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
            'fund_flow' => [
                'nodes' => $fundFlowNodes,
                'edges' => $fundFlowEdges,
                'description' => $fundFlowDescription,
                'total_amount' => $totalAmount,
                'total_cost' => $totalCost,
                'total_profit' => $totalProfit,
            ],
            'withhold_formula' => [
                'formulas' => $withholdFormulas,
                'summary' => $withholdSummary,
            ],
        ];
    }
}
