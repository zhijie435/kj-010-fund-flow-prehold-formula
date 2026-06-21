<?php

namespace Shearerline\Tests\Unit;

use Shearerline\Models\Product;
use Shearerline\Models\Settlement;
use Shearerline\Models\SettlementItem;
use Shearerline\Services\FundFlowService;
use Shearerline\Tests\TestCase;
use Illuminate\Support\Collection;

class FundFlowServiceTest extends TestCase
{
    protected FundFlowService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FundFlowService();
    }

    // ─── buildFundFlow: 正常场景 ───────────────────────────────────────────

    public function test_build_fund_flow_with_positive_profit()
    {
        $result = $this->service->buildFundFlow(
            1000.00,
            500.00,
            50.00,
            30.00,
            580.00,
            420.00,
            210.00,
            84.00,
            126.00,
            0.50,
            0.20,
            0.30
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('nodes', $result);
        $this->assertArrayHasKey('edges', $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('total_amount', $result);
        $this->assertArrayHasKey('total_cost', $result);
        $this->assertArrayHasKey('total_profit', $result);

        $this->assertCount(9, $result['nodes']);
        $this->assertCount(8, $result['edges']);

        $this->assertEquals(1000.00, $result['total_amount']);
        $this->assertEquals(580.00, $result['total_cost']);
        $this->assertEquals(420.00, $result['total_profit']);

        $nodeIds = collect($result['nodes'])->pluck('id')->toArray();
        $this->assertContains('customer', $nodeIds);
        $this->assertContains('platform', $nodeIds);
        $this->assertContains('product_cost', $nodeIds);
        $this->assertContains('platform_fee', $nodeIds);
        $this->assertContains('other_cost', $nodeIds);
        $this->assertContains('profit', $nodeIds);
        $this->assertContains('supplier', $nodeIds);
        $this->assertContains('distributor', $nodeIds);
        $this->assertContains('platform_income', $nodeIds);

        $customerNode = collect($result['nodes'])->firstWhere('id', 'customer');
        $this->assertEquals('source', $customerNode['type']);
        $this->assertEquals(1000.00, $customerNode['amount']);

        $productCostNode = collect($result['nodes'])->firstWhere('id', 'product_cost');
        $this->assertEquals(500.00, $productCostNode['amount']);

        $supplierNode = collect($result['nodes'])->firstWhere('id', 'supplier');
        $this->assertEquals(210.00, $supplierNode['amount']);
        $this->assertEquals('recipient', $supplierNode['type']);

        $edgeFromCustomer = collect($result['edges'])->firstWhere('from', 'customer');
        $this->assertEquals('platform', $edgeFromCustomer['to']);
        $this->assertEquals(1000.00, $edgeFromCustomer['amount']);

        $edgeToProfit = collect($result['edges'])->firstWhere('to', 'profit');
        $this->assertEquals(420.00, $edgeToProfit['amount']);

        $edgeSupplier = collect($result['edges'])->firstWhere('to', 'supplier');
        $this->assertEquals('50%', $edgeSupplier['label']);
        $this->assertEquals(210.00, $edgeSupplier['amount']);

        $this->assertNotEmpty($result['description']);
        $this->assertStringContainsString('客户支付', $result['description']);
        $this->assertStringContainsString('¥1000.00', $result['description']);
        $this->assertStringContainsString('供应商 50%', $result['description']);
    }

    public function test_build_fund_flow_zero_amount_edge_case()
    {
        $result = $this->service->buildFundFlow(
            0.00, 0.00, 0.00, 0.00, 0.00, 0.00,
            0.00, 0.00, 0.00,
            0.50, 0.20, 0.30
        );

        $this->assertCount(9, $result['nodes']);
        $this->assertCount(8, $result['edges']);
        $this->assertEquals(0.00, $result['total_amount']);
        $this->assertEquals(0.00, $result['total_cost']);
        $this->assertEquals(0.00, $result['total_profit']);
    }

    public function test_build_fund_flow_negative_profit_loss_scenario()
    {
        $result = $this->service->buildFundFlow(
            500.00,
            400.00,
            80.00,
            50.00,
            530.00,
            -30.00,
            -15.00,
            -6.00,
            -9.00,
            0.50,
            0.20,
            0.30
        );

        $this->assertEquals(-30.00, $result['total_profit']);
        $profitNode = collect($result['nodes'])->firstWhere('id', 'profit');
        $this->assertEquals(-30.00, $profitNode['amount']);

        $supplierNode = collect($result['nodes'])->firstWhere('id', 'supplier');
        $this->assertEquals(-15.00, $supplierNode['amount']);
    }

    public function test_build_fund_flow_product_cost_rounds_to_two_decimals()
    {
        $result = $this->service->buildFundFlow(
            100.00,
            33.333,
            10.00,
            5.00,
            48.33,
            51.67,
            25.84,
            10.33,
            15.50,
            0.50, 0.20, 0.30
        );

        $productCostNode = collect($result['nodes'])->firstWhere('id', 'product_cost');
        $this->assertEquals(33.33, $productCostNode['amount']);

        $edgeProductCost = collect($result['edges'])->firstWhere('to', 'product_cost');
        $this->assertEquals(33.33, $edgeProductCost['amount']);
    }

    // ─── buildFundFlowFromSettlement ────────────────────────────────────────

    public function test_build_fund_flow_from_settlement_with_items_loaded()
    {
        $product = Product::create([
            'name' => 'Test Course',
            'sku' => 'COURSE-001',
            'sale_price' => 299.00,
            'supplier_price' => 150.00,
            'status' => 1,
        ]);

        $settlement = Settlement::create([
            'settlement_no' => 'STL-FF-001',
            'type' => Settlement::TYPE_MANUAL,
            'settlement_date' => now()->toDateString(),
            'status' => Settlement::STATUS_PENDING,
            'platform_fee' => 20.00,
            'other_cost' => 10.00,
            'supplier_ratio' => 0.5,
            'distributor_ratio' => 0.3,
            'platform_ratio' => 0.2,
        ]);

        $settlement->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity' => 2,
            'sale_price' => 299.00,
            'total_sales' => 598.00,
            'unit_cost' => 150.00,
            'total_cost' => 300.00,
            'profit' => 298.00,
        ]);

        $settlement->recalculateTotals()->save();
        $settlement = Settlement::with('items')->find($settlement->id);

        $result = $this->service->buildFundFlowFromSettlement($settlement);

        $this->assertCount(9, $result['nodes']);
        $this->assertCount(8, $result['edges']);
        $this->assertEquals(598.00, $result['total_amount']);
        $this->assertEquals(330.00, $result['total_cost']);
        $this->assertEquals(268.00, $result['total_profit']);
    }

    public function test_build_fund_flow_from_settlement_without_items_loaded_returns_empty()
    {
        $product = Product::create([
            'name' => 'Test',
            'sku' => 'T-001',
            'sale_price' => 100,
            'status' => 1,
        ]);

        $settlement = Settlement::create([
            'settlement_no' => 'STL-FF-002',
            'type' => Settlement::TYPE_MANUAL,
            'settlement_date' => now()->toDateString(),
            'status' => Settlement::STATUS_PENDING,
        ]);

        $settlement->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity' => 1,
            'sale_price' => 100,
            'total_sales' => 100,
            'unit_cost' => 50,
            'total_cost' => 50,
            'profit' => 50,
        ]);

        $settlement = Settlement::find($settlement->id);
        $this->assertFalse($settlement->relationLoaded('items'));

        $result = $this->service->buildFundFlowFromSettlement($settlement);
        $this->assertEmpty($result['nodes']);
        $this->assertEmpty($result['edges']);
        $this->assertEquals('', $result['description']);
    }

    // ─── buildWithholdFormula: 正常场景 ─────────────────────────────────────

    public function test_build_withhold_formula_with_multiple_items()
    {
        $items = new Collection([
            new SettlementItem([
                'product_id' => 1,
                'product_name' => '课程A',
                'product_sku' => 'SKU-A',
                'quantity' => 2,
                'sale_price' => 100.00,
                'total_sales' => 200.00,
                'unit_cost' => 40.00,
                'total_cost' => 80.00,
                'profit' => 120.00,
            ]),
            new SettlementItem([
                'product_id' => 2,
                'product_name' => '课程B',
                'product_sku' => 'SKU-B',
                'quantity' => 1,
                'sale_price' => 300.00,
                'total_sales' => 300.00,
                'unit_cost' => 120.00,
                'total_cost' => 120.00,
                'profit' => 180.00,
            ]),
        ]);

        $result = $this->service->buildWithholdFormula(
            $items,
            500.00,
            200.00,
            30.00,
            20.00,
            250.00,
            250.00,
            0.50,
            125.00,
            50.00,
            75.00,
            0.50, 0.20, 0.30
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('formulas', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertCount(9, $result['formulas']);

        $formulas = collect($result['formulas']);

        $salesFormula = $formulas->firstWhere('name', '销售总额');
        $this->assertEquals(500.00, $salesFormula['value']);
        $this->assertStringContainsString('¥100 × 2', $salesFormula['calculation']);
        $this->assertStringContainsString('¥300 × 1', $salesFormula['calculation']);

        $costFormula = $formulas->firstWhere('name', '商品成本');
        $this->assertEquals(200.00, $costFormula['value']);
        $this->assertStringContainsString('¥40 × 2', $costFormula['calculation']);

        $withholdFormula = $formulas->firstWhere('name', '预扣费用合计');
        $this->assertEquals(50.00, $withholdFormula['value']);
        $this->assertEquals('¥30 + ¥20', $withholdFormula['calculation']);

        $totalCostFormula = $formulas->firstWhere('name', '总成本');
        $this->assertEquals(250.00, $totalCostFormula['value']);

        $profitFormula = $formulas->firstWhere('name', '利润总额');
        $this->assertEquals(250.00, $profitFormula['value']);
        $this->assertEquals('¥500 - ¥250', $profitFormula['calculation']);

        $profitRateFormula = $formulas->firstWhere('name', '利润率');
        $this->assertEquals(0.50, $profitRateFormula['value']);
        $this->assertTrue($profitRateFormula['is_percent']);
        $this->assertStringContainsString('× 100%', $profitRateFormula['calculation']);

        $supplierShare = $formulas->firstWhere('name', '供应商分成');
        $this->assertEquals(125.00, $supplierShare['value']);
        $this->assertStringContainsString('× 50%', $supplierShare['calculation']);

        $distributorShare = $formulas->firstWhere('name', '分销商分成');
        $this->assertEquals(50.00, $distributorShare['value']);
        $this->assertStringContainsString('× 20%', $distributorShare['calculation']);

        $platformShare = $formulas->firstWhere('name', '平台分成');
        $this->assertEquals(75.00, $platformShare['value']);
        $this->assertStringContainsString('× 30%', $platformShare['calculation']);

        $this->assertNotEmpty($result['summary']);
        $this->assertStringContainsString('2 件商品', $result['summary']);
        $this->assertStringContainsString('销售总额', $result['summary']);
        $this->assertStringContainsString('利润率', $result['summary']);
    }

    public function test_build_withhold_formula_zero_total_amount_shows_na_profit_rate()
    {
        $items = new Collection([
            new SettlementItem([
                'product_id' => 1,
                'product_name' => 'Test',
                'product_sku' => 'T-1',
                'quantity' => 1,
                'sale_price' => 0.00,
                'total_sales' => 0.00,
                'unit_cost' => 50.00,
                'total_cost' => 50.00,
                'profit' => -50.00,
            ]),
        ]);

        $result = $this->service->buildWithholdFormula(
            $items,
            0.00, 50.00, 0.00, 0.00, 50.00, -50.00, 0.00,
            -25.00, -10.00, -15.00,
            0.50, 0.20, 0.30
        );

        $profitRateFormula = collect($result['formulas'])->firstWhere('name', '利润率');
        $this->assertEquals('N/A', $profitRateFormula['calculation']);
        $this->assertStringContainsString('利润率 0%', $result['summary']);
    }

    public function test_build_withhold_formula_single_item()
    {
        $items = new Collection([
            new SettlementItem([
                'product_id' => 1,
                'product_name' => 'Single',
                'product_sku' => 'S-1',
                'quantity' => 3,
                'sale_price' => 99.99,
                'total_sales' => 299.97,
                'unit_cost' => 33.333,
                'total_cost' => 99.999,
                'profit' => 199.971,
            ]),
        ]);

        $result = $this->service->buildWithholdFormula(
            $items,
            299.97, 100.00, 15.00, 5.00, 120.00, 179.97, 0.5999,
            89.99, 35.99, 53.99,
            0.50, 0.20, 0.30
        );

        $this->assertCount(9, $result['formulas']);
        $this->assertStringContainsString('1 件商品', $result['summary']);

        $productCostFormula = collect($result['formulas'])->firstWhere('name', '商品成本');
        $this->assertEquals(100.00, $productCostFormula['value']);
    }

    public function test_build_withhold_formula_share_calculations_match_formula()
    {
        $items = new Collection([
            new SettlementItem([
                'product_id' => 1,
                'product_name' => 'X',
                'product_sku' => 'X-1',
                'quantity' => 1,
                'sale_price' => 1000,
                'total_sales' => 1000,
                'unit_cost' => 400,
                'total_cost' => 400,
                'profit' => 600,
            ]),
        ]);

        $result = $this->service->buildWithholdFormula(
            $items,
            1000, 400, 50, 50, 500, 500, 0.5,
            250, 100, 150,
            0.5, 0.2, 0.3
        );

        $formulas = collect($result['formulas']);
        $this->assertEquals(250, $formulas->firstWhere('name', '供应商分成')['value']);
        $this->assertEquals(100, $formulas->firstWhere('name', '分销商分成')['value']);
        $this->assertEquals(150, $formulas->firstWhere('name', '平台分成')['value']);
        $this->assertEquals('¥500 × 50%', $formulas->firstWhere('name', '供应商分成')['calculation']);
        $this->assertEquals('¥500 × 20%', $formulas->firstWhere('name', '分销商分成')['calculation']);
        $this->assertEquals('¥500 × 30%', $formulas->firstWhere('name', '平台分成')['calculation']);
    }

    // ─── buildWithholdFormulaFromSettlement ─────────────────────────────────

    public function test_build_withhold_formula_from_settlement_with_items_loaded()
    {
        $product = Product::create([
            'name' => 'Math Course',
            'sku' => 'MATH-101',
            'sale_price' => 199.00,
            'supplier_price' => 80.00,
            'status' => 1,
        ]);

        $settlement = Settlement::create([
            'settlement_no' => 'STL-WF-001',
            'type' => Settlement::TYPE_MANUAL,
            'settlement_date' => now()->toDateString(),
            'status' => Settlement::STATUS_PENDING,
            'platform_fee' => 15.00,
            'other_cost' => 5.00,
            'supplier_ratio' => 0.6,
            'distributor_ratio' => 0.1,
            'platform_ratio' => 0.3,
        ]);

        $settlement->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity' => 3,
            'sale_price' => 199.00,
            'total_sales' => 597.00,
            'unit_cost' => 80.00,
            'total_cost' => 240.00,
            'profit' => 357.00,
        ]);

        $settlement->recalculateTotals()->save();
        $settlement = Settlement::with('items')->find($settlement->id);

        $result = $this->service->buildWithholdFormulaFromSettlement($settlement);

        $this->assertCount(9, $result['formulas']);
        $this->assertNotEmpty($result['summary']);
        $this->assertStringContainsString('1 件商品', $result['summary']);

        $formulas = collect($result['formulas']);
        $this->assertEquals(597.00, $formulas->firstWhere('name', '销售总额')['value']);
        $this->assertEquals(240.00, $formulas->firstWhere('name', '商品成本')['value']);
    }

    public function test_build_withhold_formula_from_settlement_without_items_returns_empty()
    {
        $product = Product::create([
            'name' => 'T',
            'sku' => 'T-1',
            'sale_price' => 100,
            'status' => 1,
        ]);

        $settlement = Settlement::create([
            'settlement_no' => 'STL-WF-002',
            'type' => Settlement::TYPE_MANUAL,
            'settlement_date' => now()->toDateString(),
            'status' => Settlement::STATUS_PENDING,
        ]);

        $settlement->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity' => 1,
            'sale_price' => 100,
            'total_sales' => 100,
            'unit_cost' => 50,
            'total_cost' => 50,
            'profit' => 50,
        ]);

        $settlement = Settlement::find($settlement->id);
        $result = $this->service->buildWithholdFormulaFromSettlement($settlement);

        $this->assertEmpty($result['formulas']);
        $this->assertEquals('', $result['summary']);
    }

    // ─── buildProductCostBreakdown ──────────────────────────────────────────

    public function test_build_product_cost_breakdown_aggregates_by_type()
    {
        $items = new Collection([
            new SettlementItem([
                'quantity' => 2,
                'cost_breakdown' => [
                    ['cost_type' => 'purchase', 'cost_type_name' => '采购成本', 'total' => 50.00],
                    ['cost_type' => 'shipping', 'cost_type_name' => '物流成本', 'total' => 10.00],
                ],
            ]),
            new SettlementItem([
                'quantity' => 3,
                'cost_breakdown' => [
                    ['cost_type' => 'purchase', 'cost_type_name' => '采购成本', 'total' => 30.00],
                    ['cost_type' => 'packaging', 'cost_type_name' => '包装成本', 'total' => 5.00],
                ],
            ]),
        ]);

        $result = $this->service->buildProductCostBreakdown($items);

        $this->assertCount(3, $result);

        $types = collect($result)->pluck('cost_type')->toArray();
        $this->assertContains('purchase', $types);
        $this->assertContains('shipping', $types);
        $this->assertContains('packaging', $types);

        $purchase = collect($result)->firstWhere('cost_type', 'purchase');
        $this->assertEquals('采购成本', $purchase['cost_type_name']);
        $this->assertEquals(50 * 2 + 30 * 3, $purchase['total']);

        $shipping = collect($result)->firstWhere('cost_type', 'shipping');
        $this->assertEquals(10 * 2, $shipping['total']);

        $packaging = collect($result)->firstWhere('cost_type', 'packaging');
        $this->assertEquals(5 * 3, $packaging['total']);
    }

    public function test_build_product_cost_breakdown_empty_items_returns_empty()
    {
        $result = $this->service->buildProductCostBreakdown(new Collection([]));
        $this->assertEmpty($result);
    }

    public function test_build_product_cost_breakdown_items_without_breakdown_returns_empty()
    {
        $items = new Collection([
            new SettlementItem(['quantity' => 1]),
            new SettlementItem(['quantity' => 2, 'cost_breakdown' => null]),
        ]);

        $result = $this->service->buildProductCostBreakdown($items);
        $this->assertEmpty($result);
    }

    public function test_build_product_cost_breakdown_rounds_to_two_decimals()
    {
        $items = new Collection([
            new SettlementItem([
                'quantity' => 3,
                'cost_breakdown' => [
                    ['cost_type' => 'purchase', 'cost_type_name' => '采购成本', 'total' => 33.333],
                ],
            ]),
        ]);

        $result = $this->service->buildProductCostBreakdown($items);
        $this->assertEquals(100.00, $result[0]['total']);
    }

    // ─── empty helpers ──────────────────────────────────────────────────────

    public function test_empty_fund_flow_structure()
    {
        $result = $this->service->emptyFundFlow();
        $this->assertEquals([], $result['nodes']);
        $this->assertEquals([], $result['edges']);
        $this->assertEquals('', $result['description']);
    }

    public function test_empty_withhold_formula_structure()
    {
        $result = $this->service->emptyWithholdFormula();
        $this->assertEquals([], $result['formulas']);
        $this->assertEquals('', $result['summary']);
    }
}
