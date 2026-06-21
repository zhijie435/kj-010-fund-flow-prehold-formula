<?php

namespace Shearerline\Tests\Unit;

use Shearerline\Models\Product;
use Shearerline\Models\Settlement;
use Shearerline\Models\SettlementItem;
use Shearerline\Tests\TestCase;

class SettlementTest extends TestCase
{
    public function test_fund_flow_and_withhold_formula_are_synced_when_items_not_loaded()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'sale_price' => 100,
            'status' => 1,
        ]);

        $settlement = Settlement::create([
            'settlement_no' => 'SET-TEST-001',
            'type' => Settlement::TYPE_MANUAL,
            'settlement_date' => now()->toDateString(),
            'status' => Settlement::STATUS_PENDING,
            'platform_fee' => 10,
            'other_cost' => 5,
            'supplier_ratio' => 0.5,
            'distributor_ratio' => 0.3,
            'platform_ratio' => 0.2,
        ]);

        $settlement->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity' => 2,
            'sale_price' => 100,
            'total_sales' => 200,
            'unit_cost' => 50,
            'total_cost' => 100,
            'profit' => 100,
        ]);

        $settlement->recalculateTotals()->save();

        $settlement = Settlement::find($settlement->id);

        $this->assertFalse($settlement->relationLoaded('items'));

        $fundFlow = $settlement->fund_flow;
        $this->assertIsArray($fundFlow['nodes']);
        $this->assertCount(0, $fundFlow['nodes']);
        $this->assertCount(0, $fundFlow['edges']);
        $this->assertEquals('', $fundFlow['description']);

        $withholdFormula = $settlement->withhold_formula;
        $this->assertIsArray($withholdFormula['formulas']);
        $this->assertCount(0, $withholdFormula['formulas']);
        $this->assertEquals('', $withholdFormula['summary']);
    }

    public function test_fund_flow_and_withhold_formula_are_synced_when_items_loaded()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'sku' => 'TEST-002',
            'sale_price' => 100,
            'status' => 1,
        ]);

        $settlement = Settlement::create([
            'settlement_no' => 'SET-TEST-002',
            'type' => Settlement::TYPE_MANUAL,
            'settlement_date' => now()->toDateString(),
            'status' => Settlement::STATUS_PENDING,
            'platform_fee' => 10,
            'other_cost' => 5,
            'supplier_ratio' => 0.5,
            'distributor_ratio' => 0.3,
            'platform_ratio' => 0.2,
        ]);

        $settlement->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity' => 2,
            'sale_price' => 100,
            'total_sales' => 200,
            'unit_cost' => 50,
            'total_cost' => 100,
            'profit' => 100,
        ]);

        $settlement->recalculateTotals()->save();

        $settlement = Settlement::with('items')->find($settlement->id);
        $this->assertTrue($settlement->relationLoaded('items'));

        $fundFlow = $settlement->fund_flow;
        $this->assertCount(9, $fundFlow['nodes']);
        $this->assertCount(8, $fundFlow['edges']);
        $this->assertNotEmpty($fundFlow['description']);
        $this->assertEquals(200, $fundFlow['total_amount']);
        $this->assertEquals(85, $fundFlow['total_profit']);

        $withholdFormula = $settlement->withhold_formula;
        $this->assertCount(9, $withholdFormula['formulas']);
        $this->assertNotEmpty($withholdFormula['summary']);
    }

    public function test_all_three_accessors_respect_relation_loaded()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'sku' => 'TEST-003',
            'sale_price' => 100,
            'status' => 1,
        ]);

        $settlement = Settlement::create([
            'settlement_no' => 'SET-TEST-003',
            'type' => Settlement::TYPE_MANUAL,
            'settlement_date' => now()->toDateString(),
            'status' => Settlement::STATUS_PENDING,
            'platform_fee' => 10,
            'other_cost' => 5,
        ]);

        $settlement->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity' => 2,
            'sale_price' => 100,
            'total_sales' => 200,
            'unit_cost' => 50,
            'total_cost' => 100,
            'profit' => 100,
        ]);

        $settlement = Settlement::find($settlement->id);
        $this->assertFalse($settlement->relationLoaded('items'));

        $productCostBreakdown = $settlement->product_cost_breakdown;
        $fundFlow = $settlement->fund_flow;
        $withholdFormula = $settlement->withhold_formula;

        $this->assertCount(0, $productCostBreakdown);
        $this->assertCount(0, $fundFlow['nodes']);
        $this->assertCount(0, $withholdFormula['formulas']);

        $settlement->load('items');

        $productCostBreakdown = $settlement->product_cost_breakdown;
        $fundFlow = $settlement->fund_flow;
        $withholdFormula = $settlement->withhold_formula;

        $this->assertCount(0, $productCostBreakdown);
        $this->assertCount(9, $fundFlow['nodes']);
        $this->assertCount(9, $withholdFormula['formulas']);
    }
}
