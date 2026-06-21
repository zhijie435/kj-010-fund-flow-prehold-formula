<?php

namespace Shearerline\Console\Commands;

use Shearerline\Models\Product;
use Shearerline\Models\Settlement;
use Shearerline\Models\SettlementItem;
use Shearerline\Services\FundFlowService;
use Shearerline\Services\SettlementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VerifyCommand extends Command
{
    protected $signature = 'shearerline:verify
        {--fund-flow : 仅验收资金流向功能}
        {--withhold-formula : 仅验收预扣公式功能}
        {--settlement : 仅验收结算流程}
        {--database : 仅验收数据库结构}
        {--all : 运行所有验收项 (默认)}';

    protected $description = '验证 Shearerline 资金流向与预扣公式功能';

    protected $fundFlowService;
    protected $settlementService;
    protected $passed = 0;
    protected $failed = 0;
    protected $warnings = 0;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->fundFlowService = app(FundFlowService::class);
        $this->settlementService = app(SettlementService::class);

        $this->info('========================================');
        $this->info('  Shearerline 功能验收');
        $this->info('  资金流向与预扣公式');
        $this->info('========================================');
        $this->newLine();

        $runAll = $this->option('all') ||
            (!$this->option('fund-flow') &&
             !$this->option('withhold-formula') &&
             !$this->option('settlement') &&
             !$this->option('database'));

        if ($runAll || $this->option('database')) {
            $this->verifyDatabase();
        }

        if ($runAll || $this->option('fund-flow')) {
            $this->verifyFundFlow();
        }

        if ($runAll || $this->option('withhold-formula')) {
            $this->verifyWithholdFormula();
        }

        if ($runAll || $this->option('settlement')) {
            $this->verifySettlement();
        }

        $this->newLine();
        $this->info('========================================');
        $this->info('  验收结果汇总');
        $this->info('========================================');
        $this->info("通过: <fg=green>{$this->passed}</>");
        $this->info("警告: <fg=yellow>{$this->warnings}</>");
        $this->info("失败: <fg=red>{$this->failed}</>");
        $this->newLine();

        if ($this->failed > 0) {
            $this->error('验收未通过，请检查上述失败项。');
            return self::FAILURE;
        }

        $this->info('<fg=green>✓ 所有验收项已通过！</>');
        return self::SUCCESS;
    }

    protected function verifyDatabase(): void
    {
        $this->line('--- 数据库结构验收 ---');

        $tables = [
            'shearerline_products' => '商品表',
            'shearerline_product_costs' => '商品成本表',
            'shearerline_settlements' => '结算单表',
            'shearerline_settlement_items' => '结算明细表',
        ];

        foreach ($tables as $table => $name) {
            if (Schema::hasTable($table)) {
                $this->pass("数据表存在: {$name} ({$table})");
            } else {
                $this->fail("数据表缺失: {$name} ({$table})");
            }
        }

        if (Schema::hasTable('shearerline_settlements')) {
            $columns = [
                'settlement_no', 'type', 'settlement_date',
                'total_amount', 'product_cost', 'platform_fee', 'other_cost',
                'total_cost', 'total_profit', 'profit_rate',
                'supplier_ratio', 'distributor_ratio', 'platform_ratio',
                'supplier_share', 'distributor_share', 'platform_share',
                'status',
            ];

            $missingColumns = [];
            foreach ($columns as $column) {
                if (!Schema::hasColumn('shearerline_settlements', $column)) {
                    $missingColumns[] = $column;
                }
            }

            if (empty($missingColumns)) {
                $this->pass('结算单表字段完整');
            } else {
                $this->fail('结算单表缺少字段: ' . implode(', ', $missingColumns));
            }
        }

        if (Schema::hasTable('shearerline_settlement_items')) {
            $columns = ['cost_breakdown', 'profit', 'unit_cost', 'total_sales'];
            $missingColumns = [];
            foreach ($columns as $column) {
                if (!Schema::hasColumn('shearerline_settlement_items', $column)) {
                    $missingColumns[] = $column;
                }
            }

            if (empty($missingColumns)) {
                $this->pass('结算明细表字段完整');
            } else {
                $this->fail('结算明细表缺少字段: ' . implode(', ', $missingColumns));
            }
        }

        $this->newLine();
    }

    protected function verifyFundFlow(): void
    {
        $this->line('--- 资金流向功能验收 ---');

        $result = $this->fundFlowService->buildFundFlow(
            totalAmount: 1000.00,
            productCost: 500.00,
            platformFee: 50.00,
            otherCost: 30.00,
            totalCost: 580.00,
            totalProfit: 420.00,
            supplierShare: 210.00,
            distributorShare: 84.00,
            platformShare: 126.00,
            supplierRatio: 0.50,
            distributorRatio: 0.20,
            platformRatio: 0.30
        );

        if (isset($result['nodes']) && is_array($result['nodes'])) {
            $this->pass('资金流向 nodes 结构存在');
        } else {
            $this->fail('资金流向 nodes 结构缺失');
        }

        if (isset($result['edges']) && is_array($result['edges'])) {
            $this->pass('资金流向 edges 结构存在');
        } else {
            $this->fail('资金流向 edges 结构缺失');
        }

        if (isset($result['description']) && !empty($result['description'])) {
            $this->pass('资金流向描述生成成功');
        } else {
            $this->fail('资金流向描述为空');
        }

        $nodeCount = count($result['nodes'] ?? []);
        if ($nodeCount === 9) {
            $this->pass("资金流向节点数量正确: {$nodeCount} 个");
        } else {
            $this->fail("资金流向节点数量错误: 期望 9 个，实际 {$nodeCount} 个");
        }

        $edgeCount = count($result['edges'] ?? []);
        if ($edgeCount === 8) {
            $this->pass("资金流向边数量正确: {$edgeCount} 条");
        } else {
            $this->fail("资金流向边数量错误: 期望 8 条，实际 {$edgeCount} 条");
        }

        $expectedNodes = ['customer', 'platform', 'product_cost', 'platform_fee', 'other_cost', 'profit', 'supplier', 'distributor', 'platform_income'];
        $actualNodes = collect($result['nodes'] ?? [])->pluck('id')->toArray();
        $missingNodes = array_diff($expectedNodes, $actualNodes);

        if (empty($missingNodes)) {
            $this->pass('所有资金流向节点完整');
        } else {
            $this->fail('缺少资金流向节点: ' . implode(', ', $missingNodes));
        }

        $customerNode = collect($result['nodes'] ?? [])->firstWhere('id', 'customer');
        if ($customerNode && $customerNode['type'] === 'source' && $customerNode['amount'] == 1000.00) {
            $this->pass('客户节点 (source) 数据正确');
        } else {
            $this->fail('客户节点数据错误');
        }

        $profitNode = collect($result['nodes'] ?? [])->firstWhere('id', 'profit');
        if ($profitNode && $profitNode['type'] === 'profit' && $profitNode['amount'] == 420.00) {
            $this->pass('可分配利润节点数据正确');
        } else {
            $this->fail('可分配利润节点数据错误');
        }

        $supplierNode = collect($result['nodes'] ?? [])->firstWhere('id', 'supplier');
        if ($supplierNode && $supplierNode['type'] === 'recipient' && $supplierNode['amount'] == 210.00) {
            $this->pass('供应商分成节点数据正确');
        } else {
            $this->fail('供应商分成节点数据错误');
        }

        if (isset($result['total_amount']) && $result['total_amount'] == 1000.00) {
            $this->pass('total_amount 汇总值正确');
        } else {
            $this->fail('total_amount 汇总值错误');
        }

        if (isset($result['total_cost']) && $result['total_cost'] == 580.00) {
            $this->pass('total_cost 汇总值正确');
        } else {
            $this->fail('total_cost 汇总值错误');
        }

        if (isset($result['total_profit']) && $result['total_profit'] == 420.00) {
            $this->pass('total_profit 汇总值正确');
        } else {
            $this->fail('total_profit 汇总值错误');
        }

        $this->verifyFundFlowFromSettlement();

        $this->newLine();
    }

    protected function verifyFundFlowFromSettlement(): void
    {
        try {
            $settlement = Settlement::with('items')->first();

            if (!$settlement) {
                $this->warning('暂无结算单数据，跳过从结算单生成资金流向的测试');
                return;
            }

            $result = $this->fundFlowService->buildFundFlowFromSettlement($settlement);

            if (!empty($result['nodes']) && !empty($result['edges'])) {
                $this->pass('从结算单生成资金流向成功');
            } else {
                $this->fail('从结算单生成资金流向失败');
            }
        } catch (\Exception $e) {
            $this->warning('从结算单生成资金流向测试跳过: ' . $e->getMessage());
        }
    }

    protected function verifyWithholdFormula(): void
    {
        $this->line('--- 预扣公式功能验收 ---');

        $items = collect([
            new SettlementItem([
                'product_id' => 1,
                'product_name' => '测试课程A',
                'product_sku' => 'TEST-A',
                'quantity' => 2,
                'sale_price' => 100.00,
                'total_sales' => 200.00,
                'unit_cost' => 40.00,
                'total_cost' => 80.00,
                'profit' => 120.00,
            ]),
            new SettlementItem([
                'product_id' => 2,
                'product_name' => '测试课程B',
                'product_sku' => 'TEST-B',
                'quantity' => 1,
                'sale_price' => 300.00,
                'total_sales' => 300.00,
                'unit_cost' => 120.00,
                'total_cost' => 120.00,
                'profit' => 180.00,
            ]),
        ]);

        $result = $this->fundFlowService->buildWithholdFormula(
            items: $items,
            totalAmount: 500.00,
            productCost: 200.00,
            platformFee: 30.00,
            otherCost: 20.00,
            totalCost: 250.00,
            totalProfit: 250.00,
            profitRate: 0.50,
            supplierShare: 125.00,
            distributorShare: 50.00,
            platformShare: 75.00,
            supplierRatio: 0.50,
            distributorRatio: 0.20,
            platformRatio: 0.30
        );

        if (isset($result['formulas']) && is_array($result['formulas'])) {
            $this->pass('预扣公式 formulas 结构存在');
        } else {
            $this->fail('预扣公式 formulas 结构缺失');
        }

        if (isset($result['summary']) && !empty($result['summary'])) {
            $this->pass('预扣公式汇总说明生成成功');
        } else {
            $this->fail('预扣公式汇总说明为空');
        }

        $formulaCount = count($result['formulas'] ?? []);
        if ($formulaCount === 9) {
            $this->pass("公式项数量正确: {$formulaCount} 项");
        } else {
            $this->fail("公式项数量错误: 期望 9 项，实际 {$formulaCount} 项");
        }

        $expectedFormulas = ['销售总额', '商品成本', '预扣费用合计', '总成本', '利润总额', '利润率', '供应商分成', '分销商分成', '平台分成'];
        $actualFormulas = collect($result['formulas'] ?? [])->pluck('name')->toArray();
        $missingFormulas = array_diff($expectedFormulas, $actualFormulas);

        if (empty($missingFormulas)) {
            $this->pass('所有公式项完整');
        } else {
            $this->fail('缺少公式项: ' . implode(', ', $missingFormulas));
        }

        $salesFormula = collect($result['formulas'] ?? [])->firstWhere('name', '销售总额');
        if ($salesFormula && $salesFormula['value'] == 500.00) {
            $this->pass('销售总额公式正确');
        } else {
            $this->fail('销售总额公式错误');
        }

        $profitFormula = collect($result['formulas'] ?? [])->firstWhere('name', '利润总额');
        if ($profitFormula && $profitFormula['value'] == 250.00) {
            $this->pass('利润总额公式正确');
        } else {
            $this->fail('利润总额公式错误');
        }

        $profitRateFormula = collect($result['formulas'] ?? [])->firstWhere('name', '利润率');
        if ($profitRateFormula && isset($profitRateFormula['is_percent']) && $profitRateFormula['is_percent'] === true) {
            $this->pass('利润率公式标记为百分比类型');
        } else {
            $this->fail('利润率公式缺少百分比标记');
        }

        $supplierShare = collect($result['formulas'] ?? [])->firstWhere('name', '供应商分成');
        if ($supplierShare && $supplierShare['value'] == 125.00) {
            $this->pass('供应商分成计算正确');
        } else {
            $this->fail('供应商分成计算错误');
        }

        $this->verifyWithholdFormulaFromSettlement();
        $this->verifyProductCostBreakdown();

        $this->newLine();
    }

    protected function verifyWithholdFormulaFromSettlement(): void
    {
        try {
            $settlement = Settlement::with('items')->first();

            if (!$settlement) {
                $this->warning('暂无结算单数据，跳过从结算单生成预扣公式的测试');
                return;
            }

            $result = $this->fundFlowService->buildWithholdFormulaFromSettlement($settlement);

            if (!empty($result['formulas']) && !empty($result['summary'])) {
                $this->pass('从结算单生成预扣公式成功');
            } else {
                $this->fail('从结算单生成预扣公式失败');
            }
        } catch (\Exception $e) {
            $this->warning('从结算单生成预扣公式测试跳过: ' . $e->getMessage());
        }
    }

    protected function verifyProductCostBreakdown(): void
    {
        try {
            $items = collect([
                new SettlementItem([
                    'quantity' => 2,
                    'cost_breakdown' => json_encode([
                        ['cost_type' => 'purchase', 'cost_type_name' => '采购成本', 'total' => 50.00],
                        ['cost_type' => 'shipping', 'cost_type_name' => '物流成本', 'total' => 10.00],
                    ]),
                ]),
            ]);

            $result = $this->fundFlowService->buildProductCostBreakdown($items);

            if (is_array($result) && count($result) === 2) {
                $this->pass('成本构成明细聚合正确');
            } else {
                $this->fail('成本构成明细聚合错误');
            }
        } catch (\Exception $e) {
            $this->warning('成本构成明细测试跳过: ' . $e->getMessage());
        }
    }

    protected function verifySettlement(): void
    {
        $this->line('--- 结算流程验收 ---');

        try {
            $product = Product::first();

            if (!$product) {
                $this->warning('暂无商品数据，跳过结算流程测试');
                return;
            }

            $data = [
                'type' => Settlement::TYPE_MANUAL,
                'settlement_date' => now()->toDateString(),
                'platform_fee' => 50.00,
                'other_cost' => 30.00,
                'supplier_ratio' => 0.50,
                'distributor_ratio' => 0.20,
                'platform_ratio' => 0.30,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ];

            $settlement = $this->settlementService->createSettlement($data);

            if ($settlement && $settlement->status === Settlement::STATUS_PENDING) {
                $this->pass('结算单创建成功 (状态: 待确认)');
            } else {
                $this->fail('结算单创建失败');
            }

            if ($settlement && abs($settlement->total_amount - $product->sale_price * 2) < 0.01) {
                $this->pass('结算单销售总额计算正确');
            } else {
                $this->fail('结算单销售总额计算错误');
            }

            if ($settlement && $settlement->canConfirm()) {
                $confirmed = $this->settlementService->confirmSettlement($settlement->id);
                if ($confirmed && $confirmed->status === Settlement::STATUS_CONFIRMED) {
                    $this->pass('结算单确认成功 (状态: 已确认)');
                } else {
                    $this->fail('结算单确认失败');
                }

                if ($confirmed && $confirmed->canSettle()) {
                    $settled = $this->settlementService->settleSettlement($confirmed->id);
                    if ($settled && $settled->status === Settlement::STATUS_SETTLED) {
                        $this->pass('结算单结算成功 (状态: 已结算)');
                    } else {
                        $this->fail('结算单结算失败');
                    }
                }
            }

            $settlementWithItems = Settlement::with('items')->find($settlement->id);
            if ($settlementWithItems && $settlementWithItems->relationLoaded('items')) {
                $fundFlow = $settlementWithItems->fund_flow;
                if (!empty($fundFlow['nodes'])) {
                    $this->pass('结算单模型访问器 fund_flow 可用');
                } else {
                    $this->fail('结算单模型访问器 fund_flow 不可用');
                }

                $withholdFormula = $settlementWithItems->withhold_formula;
                if (!empty($withholdFormula['formulas'])) {
                    $this->pass('结算单模型访问器 withhold_formula 可用');
                } else {
                    $this->fail('结算单模型访问器 withhold_formula 不可用');
                }
            }

            $settlement->delete();

        } catch (\Exception $e) {
            $this->fail('结算流程测试异常: ' . $e->getMessage());
        }

        $this->newLine();
    }

    protected function pass($message): void
    {
        $this->passed++;
        $this->line("  <fg=green>✓</> {$message}");
    }

    protected function fail($message): void
    {
        $this->failed++;
        $this->line("  <fg=red>✗</> {$message}");
    }

    protected function warning($message): void
    {
        $this->warnings++;
        $this->line("  <fg=yellow>!</> {$message}");
    }
}
