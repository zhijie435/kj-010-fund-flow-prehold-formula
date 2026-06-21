<?php

namespace Shearerline\Tests\Unit;

use Shearerline\Models\Product;
use Shearerline\Models\ProductCost;
use Shearerline\Services\CostCalculationService;
use Shearerline\Tests\TestCase;

class CostCalculationServiceTest extends TestCase
{
    protected CostCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CostCalculationService();
    }

    public function test_calculate_product_cost_with_no_costs()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'sale_price' => 100,
            'status' => 1,
        ]);

        $result = $this->service->calculateProductCost($product);

        $this->assertEquals(0, $result['total_cost']);
        $this->assertEquals(100, $result['sale_price']);
        $this->assertEquals(100, $result['gross_profit']);
        $this->assertEquals(1, $result['gross_margin']);
    }

    public function test_calculate_product_cost_with_purchase_cost()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'sku' => 'TEST-002',
            'sale_price' => 100,
            'status' => 1,
        ]);

        ProductCost::create([
            'product_id' => $product->id,
            'cost_type' => 'purchase',
            'cost_name' => '采购价',
            'unit_cost' => 50,
            'quantity' => 1,
            'effective_date' => now()->toDateString(),
            'is_active' => 1,
        ]);

        $result = $this->service->calculateProductCost($product);

        $this->assertEquals(50, $result['total_cost']);
        $this->assertEquals(50, $result['gross_profit']);
        $this->assertEquals(0.5, $result['gross_margin']);
    }

    public function test_calculate_product_cost_with_multiple_cost_types()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'sku' => 'TEST-003',
            'sale_price' => 200,
            'status' => 1,
        ]);

        ProductCost::create([
            'product_id' => $product->id,
            'cost_type' => 'purchase',
            'cost_name' => '采购价',
            'unit_cost' => 80,
            'quantity' => 1,
            'effective_date' => now()->toDateString(),
            'is_active' => 1,
        ]);

        ProductCost::create([
            'product_id' => $product->id,
            'cost_type' => 'shipping',
            'cost_name' => '快递费',
            'unit_cost' => 10,
            'quantity' => 1,
            'effective_date' => now()->toDateString(),
            'is_active' => 1,
        ]);

        ProductCost::create([
            'product_id' => $product->id,
            'cost_type' => 'platform_fee',
            'cost_name' => '平台佣金',
            'unit_cost' => 10,
            'quantity' => 1,
            'effective_date' => now()->toDateString(),
            'is_active' => 1,
        ]);

        $result = $this->service->calculateProductCost($product);

        $this->assertEquals(100, $result['total_cost']);
        $this->assertEquals(100, $result['gross_profit']);
        $this->assertEquals(0.5, $result['gross_margin']);
        $this->assertCount(3, $result['breakdown']);
    }
}
