<?php

namespace Shearerline\Services;

use Shearerline\Models\Settlement;
use Illuminate\Support\Collection;

class FundFlowService
{
    public function buildFundFlowFromSettlement(Settlement $settlement): array
    {
        if (!$settlement->relationLoaded('items')) {
            return $this->emptyFundFlow();
        }

        return $this->buildFundFlow(
            (float) $settlement->total_amount,
            (float) $settlement->product_cost,
            (float) $settlement->platform_fee,
            (float) $settlement->other_cost,
            (float) $settlement->total_cost,
            (float) $settlement->total_profit,
            (float) $settlement->supplier_share,
            (float) $settlement->distributor_share,
            (float) $settlement->platform_share,
            (float) $settlement->supplier_ratio,
            (float) $settlement->distributor_ratio,
            (float) $settlement->platform_ratio
        );
    }

    public function buildFundFlow(
        float $totalAmount,
        float $productCost,
        float $platformFee,
        float $otherCost,
        float $totalCost,
        float $totalProfit,
        float $supplierShare,
        float $distributorShare,
        float $platformShare,
        float $supplierRatio,
        float $distributorRatio,
        float $platformRatio
    ): array {
        $nodes = [
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

        $edges = [
            ['from' => 'customer', 'to' => 'platform', 'amount' => $totalAmount, 'label' => '销售总额'],
            ['from' => 'platform', 'to' => 'product_cost', 'amount' => round($productCost, 2), 'label' => '商品成本'],
            ['from' => 'platform', 'to' => 'platform_fee', 'amount' => $platformFee, 'label' => '平台费用'],
            ['from' => 'platform', 'to' => 'other_cost', 'amount' => $otherCost, 'label' => '其他成本'],
            ['from' => 'platform', 'to' => 'profit', 'amount' => $totalProfit, 'label' => '剩余利润'],
            ['from' => 'profit', 'to' => 'supplier', 'amount' => $supplierShare, 'label' => ($supplierRatio * 100) . '%'],
            ['from' => 'profit', 'to' => 'distributor', 'amount' => $distributorShare, 'label' => ($distributorRatio * 100) . '%'],
            ['from' => 'profit', 'to' => 'platform_income', 'amount' => $platformShare, 'label' => ($platformRatio * 100) . '%'],
        ];

        $description = sprintf(
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

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'description' => $description,
            'total_amount' => $totalAmount,
            'total_cost' => $totalCost,
            'total_profit' => $totalProfit,
        ];
    }

    public function buildWithholdFormulaFromSettlement(Settlement $settlement): array
    {
        if (!$settlement->relationLoaded('items')) {
            return $this->emptyWithholdFormula();
        }

        return $this->buildWithholdFormula(
            $settlement->items,
            (float) $settlement->total_amount,
            (float) $settlement->product_cost,
            (float) $settlement->platform_fee,
            (float) $settlement->other_cost,
            (float) $settlement->total_cost,
            (float) $settlement->total_profit,
            (float) $settlement->profit_rate,
            (float) $settlement->supplier_share,
            (float) $settlement->distributor_share,
            (float) $settlement->platform_share,
            (float) $settlement->supplier_ratio,
            (float) $settlement->distributor_ratio,
            (float) $settlement->platform_ratio
        );
    }

    public function buildWithholdFormula(
        Collection $items,
        float $totalAmount,
        float $productCost,
        float $platformFee,
        float $otherCost,
        float $totalCost,
        float $totalProfit,
        float $profitRate,
        float $supplierShare,
        float $distributorShare,
        float $platformShare,
        float $supplierRatio,
        float $distributorRatio,
        float $platformRatio
    ): array {
        $formulas = [
            [
                'name' => '销售总额',
                'formula' => '销售总额 = Σ(商品单价 × 数量)',
                'value' => $totalAmount,
                'calculation' => $items->map(function ($item) {
                    return "¥{$item->sale_price} × {$item->quantity}";
                })->implode(' + '),
            ],
            [
                'name' => '商品成本',
                'formula' => '商品成本 = Σ(单位成本 × 数量)',
                'value' => round($productCost, 2),
                'calculation' => $items->map(function ($item) {
                    return "¥{$item->unit_cost} × {$item->quantity}";
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

        $summary = sprintf(
            '本次结算共 %d 件商品，销售总额 ¥%s，扣除各项成本 ¥%s 后，实现利润 ¥%s，利润率 %s%%。',
            $items->count(),
            format_money($totalAmount),
            format_money($totalCost),
            format_money($totalProfit),
            $totalAmount > 0 ? round($profitRate * 100, 2) : '0'
        );

        return [
            'formulas' => $formulas,
            'summary' => $summary,
        ];
    }

    public function buildProductCostBreakdown(Collection $items): array
    {
        $breakdown = [];

        foreach ($items as $item) {
            if (!empty($item->cost_breakdown)) {
                foreach ($item->cost_breakdown as $bd) {
                    $type = $bd['cost_type'];
                    if (!isset($breakdown[$type])) {
                        $breakdown[$type] = [
                            'cost_type' => $type,
                            'cost_type_name' => $bd['cost_type_name'],
                            'total' => 0,
                        ];
                    }
                    $breakdown[$type]['total'] += $bd['total'] * $item->quantity;
                }
            }
        }

        foreach ($breakdown as &$bd) {
            $bd['total'] = round($bd['total'], 2);
        }

        return array_values($breakdown);
    }

    public function emptyFundFlow(): array
    {
        return [
            'nodes' => [],
            'edges' => [],
            'description' => '',
        ];
    }

    public function emptyWithholdFormula(): array
    {
        return [
            'formulas' => [],
            'summary' => '',
        ];
    }
}
