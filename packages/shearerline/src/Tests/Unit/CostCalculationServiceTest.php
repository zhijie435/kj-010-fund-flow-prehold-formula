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

    public function test_estimate_shipping_fee_within_first_weight()
    {
        $product = Product::create([
            'name' => 'Light Product',
            'sku' => 'SHIP-001',
            'sale_price' => 100,
            'weight' => 0.5,
            'status' => 1,
        ]);

        $result = $this->service->estimateShippingFee($product, [
            'template' => 'sf_standard',
            'destination' => 'local',
            'quantity' => 1,
        ]);

        $this->assertEquals(0.5, $result['actual_weight']);
        $this->assertEquals(0, $result['volumetric_weight']);
        $this->assertEquals(0.5, $result['chargeable_weight']);
        $this->assertEquals('actual', $result['weight_basis']);
        $this->assertEquals(12.00, $result['shipping_fee']);
    }

    public function test_estimate_shipping_fee_exceeding_first_weight()
    {
        $product = Product::create([
            'name' => 'Heavy Product',
            'sku' => 'SHIP-002',
            'sale_price' => 100,
            'weight' => 2.5,
            'status' => 1,
        ]);

        $result = $this->service->estimateShippingFee($product, [
            'template' => 'sf_standard',
            'destination' => 'local',
            'quantity' => 1,
        ]);

        $this->assertEquals(2.5, $result['chargeable_weight']);
        $this->assertEquals(1.5, $result['additional_weight']);
        $this->assertEquals(2, $result['additional_units']);
        $this->assertEquals(16.00, $result['shipping_fee']);
    }

    public function test_estimate_shipping_fee_uses_volumetric_weight()
    {
        $product = Product::create([
            'name' => 'Bulky Product',
            'sku' => 'SHIP-003',
            'sale_price' => 100,
            'weight' => 0.5,
            'length' => 30,
            'width' => 20,
            'height' => 20,
            'status' => 1,
        ]);

        $result = $this->service->estimateShippingFee($product, [
            'template' => 'sf_standard',
            'destination' => 'local',
            'quantity' => 1,
        ]);

        $this->assertEquals(0.5, $result['actual_weight']);
        $this->assertEquals(2.0, $result['volumetric_weight']);
        $this->assertEquals(2.0, $result['chargeable_weight']);
        $this->assertEquals('volumetric', $result['weight_basis']);
        $this->assertEquals(14.00, $result['shipping_fee']);
    }

    public function test_estimate_shipping_fee_with_quantity()
    {
        $product = Product::create([
            'name' => 'Multi Product',
            'sku' => 'SHIP-004',
            'sale_price' => 100,
            'weight' => 0.5,
            'status' => 1,
        ]);

        $result = $this->service->estimateShippingFee($product, [
            'template' => 'sf_standard',
            'destination' => 'local',
            'quantity' => 3,
        ]);

        $this->assertEquals(1.5, $result['actual_weight']);
        $this->assertEquals(1.5, $result['chargeable_weight']);
        $this->assertEquals(0.5, $result['additional_weight']);
        $this->assertEquals(1, $result['additional_units']);
        $this->assertEquals(3, $result['quantity']);
        $this->assertEquals(14.00, $result['shipping_fee']);
    }

    public function test_estimate_shipping_fee_with_override_params()
    {
        $product = Product::create([
            'name' => 'Override Product',
            'sku' => 'SHIP-005',
            'sale_price' => 100,
            'weight' => 0.5,
            'status' => 1,
        ]);

        $result = $this->service->estimateShippingFee($product, [
            'template' => 'sf_standard',
            'destination' => 'region_2',
            'quantity' => 1,
            'weight' => 3,
        ]);

        $this->assertEquals(3, $result['actual_weight']);
        $this->assertEquals(3, $result['chargeable_weight']);
        $this->assertEquals(2, $result['additional_units']);
        $this->assertEquals(21.00, $result['first_weight_fee']);
        $this->assertEquals(7.00, $result['additional_weight_fee']);
        $this->assertEquals(35.00, $result['shipping_fee']);
    }

    public function test_estimate_shipping_fee_throws_for_invalid_template()
    {
        $product = Product::create([
            'name' => 'Invalid Template',
            'sku' => 'SHIP-006',
            'sale_price' => 100,
            'weight' => 1,
            'status' => 1,
        ]);

        $this->expectException(\Shearerline\Exceptions\ShearerlineException::class);
        $this->service->estimateShippingFee($product, [
            'template' => 'non_existent',
            'destination' => 'local',
        ]);
    }

    public function test_estimate_shipping_fee_throws_for_invalid_destination()
    {
        $product = Product::create([
            'name' => 'Invalid Destination',
            'sku' => 'SHIP-007',
            'sale_price' => 100,
            'weight' => 1,
            'status' => 1,
        ]);

        $this->expectException(\Shearerline\Exceptions\ShearerlineException::class);
        $this->service->estimateShippingFee($product, [
            'template' => 'sf_standard',
            'destination' => 'overseas',
        ]);
    }

    public function test_compare_shipping_fee_orders_by_cheapest()
    {
        $product = Product::create([
            'name' => 'Compare Product',
            'sku' => 'SHIP-008',
            'sale_price' => 100,
            'weight' => 2,
            'status' => 1,
        ]);

        $result = $this->service->compareShippingFee($product, [
            'destination' => 'local',
            'quantity' => 1,
        ]);

        $this->assertNotEmpty($result['estimates']);
        $this->assertNotNull($result['cheapest']);
        $fees = array_column($result['estimates'], 'shipping_fee');
        $sorted = $fees;
        sort($sorted);
        $this->assertEquals($sorted, $fees);
        $this->assertEquals(min($fees), $result['cheapest']['shipping_fee']);
    }
}
