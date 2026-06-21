<?php

namespace Shearerline\Tests\Unit;

use Shearerline\Models\Product;
use Shearerline\Models\Settlement;
use Shearerline\Models\SettlementItem;
use Shearerline\Tests\TestCase;

class SettlementModelTest extends TestCase
{
    protected function createProduct(array $data = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Product ' . uniqid(),
            'sku' => 'SKU-' . uniqid(),
            'sale_price' => 100.00,
            'supplier_price' => 50.00,
            'status' => 1,
        ], $data));
    }

    protected function createSettlementWithItems(array $itemsData, array $extra = []): Settlement
    {
        $settlement = Settlement::create(array_merge([
            'settlement_no' => 'STL-M-' . uniqid(),
            'type' => Settlement::TYPE_MANUAL,
            'settlement_date' => now()->toDateString(),
            'status' => Settlement::STATUS_PENDING,
            'platform_fee' => 0,
            'other_cost' => 0,
            'supplier_ratio' => 0.5,
            'distributor_ratio' => 0.2,
            'platform_ratio' => 0.3,
        ], $extra));

        foreach ($itemsData as $itemData) {
            $product = $this->createProduct($itemData['product'] ?? []);
            $cb = $itemData['cost_breakdown'] ?? null;
            $item = new SettlementItem();
            $item->fill([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'quantity' => $itemData['quantity'] ?? 1,
                'sale_price' => $itemData['sale_price'] ?? $product->sale_price,
                'unit_cost' => $itemData['unit_cost'] ?? $product->supplier_price,
            ]);
            $settlement->items()->save($item);
            if ($cb !== null) {
                \Illuminate\Support\Facades\DB::table('shearerline_settlement_items')
                    ->where('id', $item->id)
                    ->update(['cost_breakdown' => json_encode($cb)]);
                $item->cost_breakdown = $cb;
            }
        }

        return $settlement;
    }

    // ─── recalculateTotals: 正常 ──────────────────────────────────────────

    public function test_recalculate_totals_single_item_basic()
    {
        $settlement = $this->createSettlementWithItems([
            ['quantity' => 2, 'sale_price' => 100, 'unit_cost' => 40],
        ]);

        $settlement->load('items');
        $settlement->recalculateTotals()->save();
        $settlement->refresh();

        $this->assertEquals(1, $settlement->order_count);
        $this->assertEquals(200.00, (float) $settlement->total_amount);
        $this->assertEquals(80.00, (float) $settlement->product_cost);
        $this->assertEquals(80.00, (float) $settlement->total_cost);
        $this->assertEquals(120.00, (float) $settlement->total_profit);
        $this->assertEquals(0.60, (float) $settlement->profit_rate);
        $this->assertEquals(60.00, (float) $settlement->supplier_share);
        $this->assertEquals(24.00, (float) $settlement->distributor_share);
        $this->assertEquals(36.00, (float) $settlement->platform_share);
    }

    public function test_recalculate_totals_multiple_items()
    {
        $settlement = $this->createSettlementWithItems([
            ['quantity' => 2, 'sale_price' => 100, 'unit_cost' => 50],
            ['quantity' => 1, 'sale_price' => 300, 'unit_cost' => 100],
            ['quantity' => 3, 'sale_price' => 50, 'unit_cost' => 20],
        ], [
            'platform_fee' => 25,
            'other_cost' => 15,
        ]);

        $settlement->load('items');
        $settlement->recalculateTotals()->save();
        $settlement->refresh();

        $this->assertEquals(3, $settlement->order_count);
        $this->assertEquals(2 * 100 + 1 * 300 + 3 * 50, (float) $settlement->total_amount);
        $this->assertEquals(2 * 50 + 1 * 100 + 3 * 20, (float) $settlement->product_cost);
        $this->assertEquals(260 + 25 + 15, (float) $settlement->total_cost);
        $this->assertEquals(650 - 300, (float) $settlement->total_profit);
    }

    public function test_recalculate_totals_with_platform_and_other_cost()
    {
        $settlement = $this->createSettlementWithItems([
            ['quantity' => 1, 'sale_price' => 1000, 'unit_cost' => 400],
        ], [
            'platform_fee' => 50,
            'other_cost' => 30,
        ]);

        $settlement->load('items');
        $settlement->recalculateTotals()->save();
        $settlement->refresh();

        $this->assertEquals(1000.00, (float) $settlement->total_amount);
        $this->assertEquals(400.00, (float) $settlement->product_cost);
        $this->assertEquals(480.00, (float) $settlement->total_cost);
        $this->assertEquals(520.00, (float) $settlement->total_profit);
        $this->assertEquals(260.00, (float) $settlement->supplier_share);
        $this->assertEquals(104.00, (float) $settlement->distributor_share);
        $this->assertEquals(156.00, (float) $settlement->platform_share);
    }

    public function test_recalculate_totals_zero_amount_profit_rate_zero()
    {
        $settlement = $this->createSettlementWithItems([
            ['quantity' => 1, 'sale_price' => 0, 'unit_cost' => 50],
        ]);

        $settlement->load('items');
        $settlement->recalculateTotals()->save();
        $settlement->refresh();

        $this->assertEquals(0.00, (float) $settlement->total_amount);
        $this->assertEquals(0, (float) $settlement->profit_rate);
    }

    public function test_recalculate_totals_negative_profit()
    {
        $settlement = $this->createSettlementWithItems([
            ['quantity' => 1, 'sale_price' => 100, 'unit_cost' => 120],
        ], [
            'platform_fee' => 20,
            'other_cost' => 10,
        ]);

        $settlement->load('items');
        $settlement->recalculateTotals()->save();
        $settlement->refresh();

        $this->assertEquals(-50.00, (float) $settlement->total_profit);
        $this->assertEquals(-25.00, (float) $settlement->supplier_share);
        $this->assertEquals(-10.00, (float) $settlement->distributor_share);
        $this->assertEquals(-15.00, (float) $settlement->platform_share);
    }

    public function test_recalculate_totals_no_items()
    {
        $settlement = Settlement::create([
            'settlement_no' => 'STL-EMPTY-' . uniqid(),
            'type' => Settlement::TYPE_MANUAL,
            'settlement_date' => now()->toDateString(),
            'status' => Settlement::STATUS_PENDING,
            'platform_fee' => 30,
            'other_cost' => 20,
        ]);
        $settlement->load('items');

        $settlement->recalculateTotals()->save();
        $settlement->refresh();

        $this->assertEquals(0, $settlement->order_count);
        $this->assertEquals(0.00, (float) $settlement->total_amount);
        $this->assertEquals(0.00, (float) $settlement->product_cost);
        $this->assertEquals(50.00, (float) $settlement->total_cost);
        $this->assertEquals(-50.00, (float) $settlement->total_profit);
    }

    public function test_recalculate_totals_uses_default_ratios_when_not_set()
    {
        $settlement = $this->createSettlementWithItems([
            ['quantity' => 1, 'sale_price' => 100, 'unit_cost' => 50],
        ]);

        $settlement->load('items');
        $settlement->recalculateTotals()->save();
        $settlement->refresh();

        $this->assertEquals(25.00, (float) $settlement->supplier_share);
        $this->assertEquals(10.00, (float) $settlement->distributor_share);
        $this->assertEquals(15.00, (float) $settlement->platform_share);
    }

    public function test_recalculate_totals_custom_ratios()
    {
        $settlement = $this->createSettlementWithItems([
            ['quantity' => 1, 'sale_price' => 1000, 'unit_cost' => 400],
        ], [
            'supplier_ratio' => 0.6,
            'distributor_ratio' => 0.1,
            'platform_ratio' => 0.3,
        ]);

        $settlement->load('items');
        $settlement->recalculateTotals()->save();
        $settlement->refresh();

        $this->assertEquals(360.00, (float) $settlement->supplier_share);
        $this->assertEquals(60.00, (float) $settlement->distributor_share);
        $this->assertEquals(180.00, (float) $settlement->platform_share);
    }

    // ─── Accessors: fund_flow / withhold_formula / product_cost_breakdown ─

    public function test_fund_flow_accessor_without_items_relation_empty()
    {
        $settlement = $this->createSettlementWithItems([
            ['quantity' => 1, 'sale_price' => 100, 'unit_cost' => 50],
        ]);
        $settlement = Settlement::find($settlement->id);

        $this->assertFalse($settlement->relationLoaded('items'));
        $this->assertEmpty($settlement->fund_flow['nodes']);
        $this->assertEmpty($settlement->fund_flow['edges']);
    }

    public function test_fund_flow_accessor_with_items_relation_loaded()
    {
        $settlement = $this->createSettlementWithItems([
            ['quantity' => 2, 'sale_price' => 100, 'unit_cost' => 40],
        ]);
        $settlement->load('items');
        $settlement->recalculateTotals()->save();
        $settlement = Settlement::with('items')->find($settlement->id);

        $this->assertCount(9, $settlement->fund_flow['nodes']);
        $this->assertCount(8, $settlement->fund_flow['edges']);
        $this->assertEquals(200.00, $settlement->fund_flow['total_amount']);
    }

    public function test_withhold_formula_accessor_without_items_relation_empty()
    {
        $settlement = $this->createSettlementWithItems([
            ['quantity' => 1, 'sale_price' => 100, 'unit_cost' => 50],
        ]);
        $settlement = Settlement::find($settlement->id);

        $this->assertEmpty($settlement->withhold_formula['formulas']);
        $this->assertEquals('', $settlement->withhold_formula['summary']);
    }

    public function test_withhold_formula_accessor_with_items_loaded()
    {
        $settlement = $this->createSettlementWithItems([
            ['quantity' => 2, 'sale_price' => 200, 'unit_cost' => 80],
        ]);
        $settlement->load('items');
        $settlement->recalculateTotals()->save();
        $settlement = Settlement::with('items')->find($settlement->id);

        $this->assertCount(9, $settlement->withhold_formula['formulas']);
        $this->assertNotEmpty($settlement->withhold_formula['summary']);
    }

    public function test_product_cost_breakdown_accessor_without_relation_empty()
    {
        $settlement = $this->createSettlementWithItems([
            [
                'quantity' => 2,
                'sale_price' => 100,
                'unit_cost' => 50,
                'cost_breakdown' => [
                    ['cost_type' => 'purchase', 'cost_type_name' => '采购', 'total' => 50],
                ],
            ],
        ]);
        $settlement = Settlement::find($settlement->id);

        $this->assertEmpty($settlement->product_cost_breakdown);
    }

    public function test_product_cost_breakdown_accessor_with_relation_and_breakdown()
    {
        $settlement = $this->createSettlementWithItems([
            [
                'quantity' => 2,
                'sale_price' => 100,
                'unit_cost' => 60,
                'cost_breakdown' => [
                    ['cost_type' => 'purchase', 'cost_type_name' => '采购成本', 'total' => 50],
                    ['cost_type' => 'shipping', 'cost_type_name' => '物流成本', 'total' => 10],
                ],
            ],
            [
                'quantity' => 1,
                'sale_price' => 200,
                'unit_cost' => 90,
                'cost_breakdown' => [
                    ['cost_type' => 'purchase', 'cost_type_name' => '采购成本', 'total' => 80],
                    ['cost_type' => 'packaging', 'cost_type_name' => '包装成本', 'total' => 10],
                ],
            ],
        ]);

        $settlement = Settlement::with('items')->find($settlement->id);
        $breakdown = $settlement->product_cost_breakdown;

        $this->assertCount(3, $breakdown);

        $purchase = collect($breakdown)->firstWhere('cost_type', 'purchase');
        $this->assertEquals(50 * 2 + 80 * 1, $purchase['total']);

        $shipping = collect($breakdown)->firstWhere('cost_type', 'shipping');
        $this->assertEquals(10 * 2, $shipping['total']);
    }

    // ─── Scopes ────────────────────────────────────────────────────────────

    public function test_scope_pending_filters_correctly()
    {
        $this->createSettlementWithItems([], ['status' => Settlement::STATUS_PENDING]);
        $this->createSettlementWithItems([], ['status' => Settlement::STATUS_CONFIRMED]);

        $this->assertEquals(1, Settlement::pending()->count());
    }

    public function test_scope_of_status_and_type()
    {
        $this->createSettlementWithItems([], ['status' => Settlement::STATUS_PENDING, 'type' => Settlement::TYPE_ORDER]);
        $this->createSettlementWithItems([], ['status' => Settlement::STATUS_PENDING, 'type' => Settlement::TYPE_MANUAL]);

        $this->assertEquals(2, Settlement::ofStatus(Settlement::STATUS_PENDING)->count());
        $this->assertEquals(1, Settlement::ofType(Settlement::TYPE_ORDER)->count());
    }

    public function test_scope_between_dates()
    {
        $this->createSettlementWithItems([], ['settlement_date' => '2024-06-15']);
        $this->createSettlementWithItems([], ['settlement_date' => '2024-07-10']);

        $count = Settlement::betweenDates('2024-06-01', '2024-06-30')->count();
        $this->assertEquals(1, $count);
    }

    // ─── SettlementItem boot: 自动计算 ────────────────────────────────────

    public function test_settlement_item_saving_auto_calculates_fields()
    {
        $product = $this->createProduct();
        $settlement = $this->createSettlementWithItems([]);

        $item = new SettlementItem([
            'settlement_id' => $settlement->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity' => 3,
            'sale_price' => 99.99,
            'unit_cost' => 33.33,
        ]);
        $item->save();
        $item->refresh();

        $this->assertEquals(299.97, (float) $item->total_sales);
        $this->assertEquals(99.99, (float) $item->total_cost);
        $this->assertEquals(199.98, (float) $item->profit);
    }

    public function test_settlement_item_profit_rate_attribute()
    {
        $product = $this->createProduct();
        $settlement = $this->createSettlementWithItems([]);

        $item = SettlementItem::create([
            'settlement_id' => $settlement->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity' => 1,
            'sale_price' => 200,
            'total_sales' => 200,
            'unit_cost' => 80,
            'total_cost' => 80,
            'profit' => 120,
        ]);

        $this->assertEquals(0.60, $item->profit_rate);
    }

    public function test_settlement_item_zero_sales_profit_rate_zero()
    {
        $product = $this->createProduct();
        $settlement = $this->createSettlementWithItems([]);

        $item = SettlementItem::create([
            'settlement_id' => $settlement->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity' => 1,
            'sale_price' => 0,
            'total_sales' => 0,
            'unit_cost' => 50,
            'total_cost' => 50,
            'profit' => -50,
        ]);

        $this->assertEquals(0, $item->profit_rate);
    }
}
