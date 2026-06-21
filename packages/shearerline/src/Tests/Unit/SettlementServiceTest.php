<?php

namespace Shearerline\Tests\Unit;

use Shearerline\Exceptions\SettlementStateException;
use Shearerline\Models\Product;
use Shearerline\Models\Settlement;
use Shearerline\Models\SettlementItem;
use Shearerline\Services\SettlementService;
use Shearerline\Tests\TestCase;

class SettlementServiceTest extends TestCase
{
    protected SettlementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SettlementService();
    }

    protected function createProduct(array $data = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Default Product',
            'sku' => 'SKU-' . uniqid(),
            'sale_price' => 100.00,
            'supplier_price' => 50.00,
            'status' => 1,
        ], $data));
    }

    // ─── createSettlement: 正常场景 ────────────────────────────────────────

    public function test_create_settlement_with_items_success()
    {
        $product = $this->createProduct(['sale_price' => 299.00, 'supplier_price' => 120.00]);

        $data = [
            'type' => Settlement::TYPE_MANUAL,
            'settlement_date' => '2024-06-01',
            'platform_fee' => 20.00,
            'other_cost' => 10.00,
            'supplier_ratio' => 0.5,
            'distributor_ratio' => 0.3,
            'platform_ratio' => 0.2,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'sale_price' => 299.00,
                    'unit_cost' => 120.00,
                ],
            ],
        ];

        $settlement = $this->service->createSettlement($data);

        $this->assertInstanceOf(Settlement::class, $settlement);
        $this->assertNotNull($settlement->id);
        $this->assertNotEmpty($settlement->settlement_no);
        $this->assertEquals(Settlement::TYPE_MANUAL, $settlement->type);
        $this->assertEquals('2024-06-01', is_string($settlement->settlement_date) ? $settlement->settlement_date : $settlement->settlement_date->toDateString());
        $this->assertEquals(Settlement::STATUS_PENDING, $settlement->status);
        $this->assertTrue($settlement->relationLoaded('items'));
        $this->assertCount(1, $settlement->items);

        $item = $settlement->items->first();
        $this->assertEquals($product->id, $item->product_id);
        $this->assertEquals(2, $item->quantity);
        $this->assertEquals(299.00, (float) $item->sale_price);
        $this->assertEquals(120.00, (float) $item->unit_cost);
        $this->assertEquals(598.00, (float) $item->total_sales);
        $this->assertEquals(240.00, (float) $item->total_cost);
        $this->assertEquals(358.00, (float) $item->profit);

        $this->assertEquals(1, $settlement->order_count);
        $this->assertEquals(598.00, (float) $settlement->total_amount);
        $this->assertEquals(240.00, (float) $settlement->product_cost);
        $this->assertEquals(270.00, (float) $settlement->total_cost);
        $this->assertEquals(328.00, (float) $settlement->total_profit);
        $this->assertEquals(0.5485, (float) $settlement->profit_rate);
        $this->assertEquals(164.00, (float) $settlement->supplier_share);
        $this->assertEquals(98.40, (float) $settlement->distributor_share);
        $this->assertEquals(65.60, (float) $settlement->platform_share);
    }

    public function test_create_settlement_auto_generates_settlement_no()
    {
        $data = [
            'type' => Settlement::TYPE_MANUAL,
            'settlement_date' => '2024-06-01',
        ];

        $settlement = $this->service->createSettlement($data);

        $this->assertNotEmpty($settlement->settlement_no);
        $this->assertStringStartsWith('STL', $settlement->settlement_no);
    }

    public function test_create_settlement_with_multiple_items()
    {
        $p1 = $this->createProduct(['name' => 'Course A', 'sale_price' => 100, 'supplier_price' => 40]);
        $p2 = $this->createProduct(['name' => 'Course B', 'sale_price' => 200, 'supplier_price' => 80]);

        $data = [
            'items' => [
                ['product_id' => $p1->id, 'quantity' => 2, 'sale_price' => 100, 'unit_cost' => 40],
                ['product_id' => $p2->id, 'quantity' => 1, 'sale_price' => 200, 'unit_cost' => 80],
            ],
        ];

        $settlement = $this->service->createSettlement($data);

        $this->assertCount(2, $settlement->items);
        $this->assertEquals(2, $settlement->order_count);
        $this->assertEquals(400.00, (float) $settlement->total_amount);
        $this->assertEquals(160.00, (float) $settlement->product_cost);
        $this->assertEquals(240.00, (float) $settlement->total_profit);
    }

    public function test_create_settlement_without_items_has_zero_totals()
    {
        $settlement = $this->service->createSettlement([
            'platform_fee' => 10,
            'other_cost' => 5,
        ]);

        $this->assertEquals(0, $settlement->order_count);
        $this->assertEquals(0.00, (float) $settlement->total_amount);
        $this->assertEquals(0.00, (float) $settlement->product_cost);
        $this->assertEquals(15.00, (float) $settlement->total_cost);
        $this->assertEquals(-15.00, (float) $settlement->total_profit);
    }

    public function test_create_settlement_applies_default_ratios_from_config()
    {
        $settlement = $this->service->createSettlement([]);

        $this->assertEquals(0.50, (float) $settlement->supplier_ratio);
        $this->assertEquals(0.20, (float) $settlement->distributor_ratio);
        $this->assertEquals(0.30, (float) $settlement->platform_ratio);
    }

    public function test_create_settlement_skips_invalid_items_missing_product_id()
    {
        $data = [
            'items' => [
                ['quantity' => 2, 'sale_price' => 100, 'unit_cost' => 50],
            ],
        ];

        $settlement = $this->service->createSettlement($data);
        $this->assertCount(0, $settlement->items);
    }

    public function test_create_settlement_skips_nonexistent_product()
    {
        $data = [
            'items' => [
                ['product_id' => 99999, 'quantity' => 1],
            ],
        ];

        $settlement = $this->service->createSettlement($data);
        $this->assertCount(0, $settlement->items);
    }

    // ─── updateSettlement: 正常场景 ────────────────────────────────────────

    public function test_update_settlement_pending_status_success()
    {
        $product = $this->createProduct();

        $settlement = $this->service->createSettlement([
            'status' => Settlement::STATUS_PENDING,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'sale_price' => 100, 'unit_cost' => 50],
            ],
        ]);

        $updated = $this->service->updateSettlement($settlement->id, [
            'remark' => 'Updated remark',
            'platform_fee' => 15,
        ]);

        $this->assertEquals('Updated remark', $updated->remark);
        $this->assertEquals(15.00, (float) $updated->platform_fee);
    }

    public function test_update_settlement_replaces_items()
    {
        $p1 = $this->createProduct(['name' => 'Old']);
        $p2 = $this->createProduct(['name' => 'New', 'sale_price' => 500]);

        $settlement = $this->service->createSettlement([
            'items' => [
                ['product_id' => $p1->id, 'quantity' => 1, 'sale_price' => 100, 'unit_cost' => 50],
            ],
        ]);

        $updated = $this->service->updateSettlement($settlement->id, [
            'items' => [
                ['product_id' => $p2->id, 'quantity' => 2, 'sale_price' => 500, 'unit_cost' => 200],
            ],
        ]);

        $this->assertCount(1, $updated->items);
        $this->assertEquals('New', $updated->items->first()->product_name);
        $this->assertEquals(1000.00, (float) $updated->total_amount);
    }

    // ─── updateSettlement: 异常分支 ────────────────────────────────────────

    public function test_update_settlement_confirmed_status_throws_exception()
    {
        $settlement = Settlement::create([
            'settlement_no' => 'STL-UPD-001',
            'type' => Settlement::TYPE_MANUAL,
            'settlement_date' => now()->toDateString(),
            'status' => Settlement::STATUS_CONFIRMED,
        ]);

        $this->expectException(SettlementStateException::class);
        $this->expectExceptionMessage('结算单当前状态不可编辑');

        $this->service->updateSettlement($settlement->id, ['remark' => 'should fail']);
    }

    public function test_update_settlement_settled_status_throws_exception()
    {
        $settlement = Settlement::create([
            'settlement_no' => 'STL-UPD-002',
            'type' => Settlement::TYPE_MANUAL,
            'settlement_date' => now()->toDateString(),
            'status' => Settlement::STATUS_SETTLED,
        ]);

        $this->expectException(SettlementStateException::class);

        $this->service->updateSettlement($settlement->id, ['remark' => 'fail']);
    }

    public function test_update_settlement_nonexistent_id_throws_exception()
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service->updateSettlement(999999, ['remark' => 'not found']);
    }

    // ─── confirmSettlement: 正常与异常 ─────────────────────────────────────

    public function test_confirm_settlement_pending_with_items_success()
    {
        $product = $this->createProduct();

        $settlement = $this->service->createSettlement([
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'sale_price' => 100, 'unit_cost' => 50],
            ],
        ]);

        $confirmed = $this->service->confirmSettlement($settlement->id);

        $this->assertEquals(Settlement::STATUS_CONFIRMED, $confirmed->status);
    }

    public function test_confirm_settlement_without_items_throws_exception()
    {
        $settlement = Settlement::create([
            'settlement_no' => 'STL-CONF-001',
            'type' => Settlement::TYPE_MANUAL,
            'settlement_date' => now()->toDateString(),
            'status' => Settlement::STATUS_PENDING,
        ]);

        $this->expectException(SettlementStateException::class);
        $this->expectExceptionMessage('结算单没有明细项，无法确认');

        $this->service->confirmSettlement($settlement->id);
    }

    public function test_confirm_settlement_already_confirmed_throws_exception()
    {
        $product = $this->createProduct();
        $settlement = $this->service->createSettlement([
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'sale_price' => 100, 'unit_cost' => 50],
            ],
        ]);

        $this->service->confirmSettlement($settlement->id);

        $this->expectException(SettlementStateException::class);
        $this->service->confirmSettlement($settlement->id);
    }

    public function test_confirm_settlement_cancelled_throws_exception()
    {
        $settlement = Settlement::create([
            'settlement_no' => 'STL-CONF-002',
            'type' => Settlement::TYPE_MANUAL,
            'settlement_date' => now()->toDateString(),
            'status' => Settlement::STATUS_CANCELLED,
        ]);

        $this->expectException(SettlementStateException::class);

        $this->service->confirmSettlement($settlement->id);
    }

    // ─── settleSettlement: 正常与异常 ──────────────────────────────────────

    public function test_settle_settlement_confirmed_status_success()
    {
        $product = $this->createProduct();
        $settlement = $this->service->createSettlement([
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'sale_price' => 100, 'unit_cost' => 50],
            ],
        ]);
        $settlement = $this->service->confirmSettlement($settlement->id);

        $settled = $this->service->settleSettlement($settlement->id);

        $this->assertEquals(Settlement::STATUS_SETTLED, $settled->status);
        $this->assertNotNull($settled->settled_at);
    }

    public function test_settle_settlement_pending_status_throws_exception()
    {
        $product = $this->createProduct();
        $settlement = $this->service->createSettlement([
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'sale_price' => 100, 'unit_cost' => 50],
            ],
        ]);

        $this->expectException(SettlementStateException::class);
        $this->service->settleSettlement($settlement->id);
    }

    public function test_settle_settlement_settled_throws_exception()
    {
        $product = $this->createProduct();
        $settlement = $this->service->createSettlement([
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'sale_price' => 100, 'unit_cost' => 50],
            ],
        ]);
        $settlement = $this->service->confirmSettlement($settlement->id);
        $settlement = $this->service->settleSettlement($settlement->id);

        $this->expectException(SettlementStateException::class);
        $this->service->settleSettlement($settlement->id);
    }

    // ─── cancelSettlement: 正常与异常 ──────────────────────────────────────

    public function test_cancel_settlement_pending_success()
    {
        $product = $this->createProduct();
        $settlement = $this->service->createSettlement([
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'sale_price' => 100, 'unit_cost' => 50],
            ],
        ]);

        $cancelled = $this->service->cancelSettlement($settlement->id);

        $this->assertEquals(Settlement::STATUS_CANCELLED, $cancelled->status);
    }

    public function test_cancel_settlement_confirmed_success()
    {
        $product = $this->createProduct();
        $settlement = $this->service->createSettlement([
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'sale_price' => 100, 'unit_cost' => 50],
            ],
        ]);
        $settlement = $this->service->confirmSettlement($settlement->id);

        $cancelled = $this->service->cancelSettlement($settlement->id);

        $this->assertEquals(Settlement::STATUS_CANCELLED, $cancelled->status);
    }

    public function test_cancel_settlement_settled_throws_exception()
    {
        $product = $this->createProduct();
        $settlement = $this->service->createSettlement([
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'sale_price' => 100, 'unit_cost' => 50],
            ],
        ]);
        $settlement = $this->service->confirmSettlement($settlement->id);
        $settlement = $this->service->settleSettlement($settlement->id);

        $this->expectException(SettlementStateException::class);
        $this->service->cancelSettlement($settlement->id);
    }

    public function test_cancel_settlement_already_cancelled_throws_exception()
    {
        $settlement = Settlement::create([
            'settlement_no' => 'STL-CANCEL-001',
            'type' => Settlement::TYPE_MANUAL,
            'settlement_date' => now()->toDateString(),
            'status' => Settlement::STATUS_CANCELLED,
        ]);

        $this->expectException(SettlementStateException::class);
        $this->service->cancelSettlement($settlement->id);
    }

    // ─── calculateSettlement: 正常场景 ─────────────────────────────────────

    public function test_calculate_settlement_basic_flow()
    {
        $product = $this->createProduct(['sale_price' => 200, 'supplier_price' => 80]);

        $result = $this->service->calculateSettlement(
            [
                ['product_id' => $product->id, 'quantity' => 3, 'sale_price' => 200, 'unit_cost' => 80],
            ],
            [
                'platform_fee' => 30,
                'other_cost' => 20,
                'supplier_ratio' => 0.5,
                'distributor_ratio' => 0.2,
                'platform_ratio' => 0.3,
            ]
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('shares', $result);
        $this->assertArrayHasKey('fund_flow', $result);
        $this->assertArrayHasKey('withhold_formula', $result);

        $this->assertCount(1, $result['items']);
        $this->assertEquals(1, $result['summary']['order_count']);
        $this->assertEquals(600.00, $result['summary']['total_amount']);
        $this->assertEquals(240.00, $result['summary']['product_cost']);
        $this->assertEquals(30.00, $result['summary']['platform_fee']);
        $this->assertEquals(20.00, $result['summary']['other_cost']);
        $this->assertEquals(290.00, $result['summary']['total_cost']);
        $this->assertEquals(310.00, $result['summary']['total_profit']);
        $this->assertEquals(0.5167, $result['summary']['profit_rate']);

        $this->assertEquals(0.5, $result['shares']['supplier_ratio']);
        $this->assertEquals(0.2, $result['shares']['distributor_ratio']);
        $this->assertEquals(0.3, $result['shares']['platform_ratio']);
        $this->assertEquals(155.00, $result['shares']['supplier_share']);
        $this->assertEquals(62.00, $result['shares']['distributor_share']);
        $this->assertEquals(93.00, $result['shares']['platform_share']);

        $this->assertCount(9, $result['fund_flow']['nodes']);
        $this->assertCount(9, $result['withhold_formula']['formulas']);
    }

    public function test_calculate_settlement_with_cost_breakdown_from_product_costs()
    {
        $product = $this->createProduct(['sale_price' => 500, 'supplier_price' => 200]);

        $product->costs()->create([
            'cost_type' => 'purchase',
            'cost_name' => '采购成本',
            'unit_cost' => 150.00,
            'quantity' => 1,
            'total_cost' => 150.00,
            'effective_date' => now()->subDay(),
            'is_active' => 1,
        ]);
        $product->costs()->create([
            'cost_type' => 'shipping',
            'cost_name' => '物流成本',
            'unit_cost' => 20.00,
            'quantity' => 1,
            'total_cost' => 20.00,
            'effective_date' => now()->subDay(),
            'is_active' => 1,
        ]);

        $result = $this->service->calculateSettlement(
            [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            ['platform_fee' => 10, 'other_cost' => 5]
        );

        $this->assertCount(1, $result['items']);
        $item = $result['items'][0];
        $this->assertEquals(170.00, $item['unit_cost']);
        $this->assertEquals(340.00, $item['total_cost']);
        $this->assertNotNull($item['cost_breakdown']);

        $this->assertNotEmpty($result['summary']['product_cost_breakdown']);
        $types = collect($result['summary']['product_cost_breakdown'])->pluck('cost_type')->toArray();
        $this->assertContains('purchase', $types);
        $this->assertContains('shipping', $types);
    }

    public function test_calculate_settlement_empty_items_returns_zero_summary()
    {
        $result = $this->service->calculateSettlement([], [
            'platform_fee' => 50,
            'other_cost' => 25,
        ]);

        $this->assertCount(0, $result['items']);
        $this->assertEquals(0, $result['summary']['order_count']);
        $this->assertEquals(0.00, $result['summary']['total_amount']);
        $this->assertEquals(0.00, $result['summary']['product_cost']);
        $this->assertEquals(75.00, $result['summary']['total_cost']);
        $this->assertEquals(-75.00, $result['summary']['total_profit']);
    }

    public function test_calculate_settlement_skips_invalid_product_items()
    {
        $product = $this->createProduct();

        $result = $this->service->calculateSettlement([
            ['product_id' => 999999, 'quantity' => 1],
            ['quantity' => 2],
            ['product_id' => $product->id, 'quantity' => 1, 'sale_price' => 100, 'unit_cost' => 50],
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertEquals(1, $result['summary']['order_count']);
    }

    public function test_calculate_settlement_defaults_to_config_ratios()
    {
        $product = $this->createProduct();

        $result = $this->service->calculateSettlement([
            ['product_id' => $product->id, 'quantity' => 1, 'sale_price' => 100, 'unit_cost' => 50],
        ]);

        $this->assertEquals(0.50, $result['shares']['supplier_ratio']);
        $this->assertEquals(0.20, $result['shares']['distributor_ratio']);
        $this->assertEquals(0.30, $result['shares']['platform_ratio']);
    }

    public function test_calculate_settlement_zero_amount_profit_rate_is_zero()
    {
        $product = $this->createProduct();

        $result = $this->service->calculateSettlement([
            ['product_id' => $product->id, 'quantity' => 1, 'sale_price' => 0, 'unit_cost' => 50],
        ]);

        $this->assertEquals(0, $result['summary']['profit_rate']);
    }

    public function test_calculate_settlement_item_profit_rate_calculated()
    {
        $product = $this->createProduct();

        $result = $this->service->calculateSettlement([
            ['product_id' => $product->id, 'quantity' => 2, 'sale_price' => 200, 'unit_cost' => 120],
        ]);

        $item = $result['items'][0];
        $this->assertEquals(400.00, $item['total_sales']);
        $this->assertEquals(240.00, $item['total_cost']);
        $this->assertEquals(160.00, $item['profit']);
        $this->assertEquals(0.40, $item['profit_rate']);
    }

    public function test_calculate_settlement_fund_flow_contains_correct_values()
    {
        $product = $this->createProduct();

        $result = $this->service->calculateSettlement(
            [['product_id' => $product->id, 'quantity' => 1, 'sale_price' => 1000, 'unit_cost' => 400]],
            ['platform_fee' => 50, 'other_cost' => 50, 'supplier_ratio' => 0.5, 'distributor_ratio' => 0.2, 'platform_ratio' => 0.3]
        );

        $ff = $result['fund_flow'];
        $this->assertEquals(1000.00, $ff['total_amount']);
        $this->assertEquals(500.00, $ff['total_cost']);
        $this->assertEquals(500.00, $ff['total_profit']);

        $shares = collect($ff['nodes'])->whereIn('id', ['supplier', 'distributor', 'platform_income']);
        $this->assertEquals(250.00, $shares->firstWhere('id', 'supplier')['amount']);
        $this->assertEquals(100.00, $shares->firstWhere('id', 'distributor')['amount']);
        $this->assertEquals(150.00, $shares->firstWhere('id', 'platform_income')['amount']);
    }

    public function test_calculate_settlement_withhold_formula_summary_contains_correct_info()
    {
        $product = $this->createProduct(['name' => 'VIP课程']);

        $result = $this->service->calculateSettlement([
            ['product_id' => $product->id, 'quantity' => 5, 'sale_price' => 200, 'unit_cost' => 80],
        ]);

        $wf = $result['withhold_formula'];
        $this->assertCount(9, $wf['formulas']);
        $this->assertStringContainsString('1 件商品', $wf['summary']);
        $this->assertStringContainsString('销售总额', $wf['summary']);
    }
}
