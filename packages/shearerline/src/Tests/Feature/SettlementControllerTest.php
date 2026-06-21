<?php

namespace Shearerline\Tests\Feature;

use Shearerline\Models\Product;
use Shearerline\Models\Settlement;
use Shearerline\ShearerlineServiceProvider;
use Shearerline\Tests\TestCase;

class SettlementControllerTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ShearerlineServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['shearerline.api_middleware' => []]);
    }

    protected function createProduct(array $data = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Product ' . uniqid(),
            'sku' => 'SKU-' . uniqid(),
            'sale_price' => 200.00,
            'supplier_price' => 80.00,
            'status' => 1,
        ], $data));
    }

    protected function createSettlement(string $status = Settlement::STATUS_PENDING, bool $withItems = true): Settlement
    {
        $settlement = Settlement::create([
            'settlement_no' => 'STL-FT-' . uniqid(),
            'type' => Settlement::TYPE_MANUAL,
            'settlement_date' => now()->toDateString(),
            'status' => $status,
            'platform_fee' => 10,
            'other_cost' => 5,
            'supplier_ratio' => 0.5,
            'distributor_ratio' => 0.2,
            'platform_ratio' => 0.3,
        ]);

        if ($withItems) {
            $product = $this->createProduct();
            $settlement->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'quantity' => 2,
                'sale_price' => 200,
                'total_sales' => 400,
                'unit_cost' => 80,
                'total_cost' => 160,
                'profit' => 240,
            ]);
            $settlement->refresh();
            $settlement->recalculateTotals()->save();
        }

        return Settlement::find($settlement->id);
    }

    protected function api($method, $uri, $data = [])
    {
        $prefix = config('shearerline.api_route_prefix', 'api/shearerline');
        return $this->json($method, "/{$prefix}/{$uri}", $data);
    }

    // ─── GET /settlements/types ────────────────────────────────────────────

    public function test_get_settlement_types_success()
    {
        $response = $this->api('GET', 'settlements/types');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'code', 'message', 'data' => ['types', 'statuses'],
            ]);

        $data = $response->json('data');
        $this->assertArrayHasKey('manual', $data['types']);
        $this->assertArrayHasKey('pending', $data['statuses']);
    }

    // ─── POST /settlements/calculate ───────────────────────────────────────

    public function test_calculate_settlement_api_success()
    {
        $product = $this->createProduct(['sale_price' => 299, 'supplier_price' => 120]);

        $response = $this->api('POST', 'settlements/calculate', [
            'settlement_date' => '2024-06-01',
            'platform_fee' => 20,
            'other_cost' => 10,
            'supplier_ratio' => 0.5,
            'distributor_ratio' => 0.3,
            'platform_ratio' => 0.2,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'sale_price' => 299,
                    'unit_cost' => 120,
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'code', 'message',
                'data' => [
                    'settlement_date',
                    'items',
                    'summary' => [
                        'order_count', 'total_amount', 'product_cost',
                        'platform_fee', 'other_cost', 'total_cost',
                        'total_profit', 'profit_rate',
                    ],
                    'shares' => [
                        'supplier_ratio', 'distributor_ratio', 'platform_ratio',
                        'supplier_share', 'distributor_share', 'platform_share',
                    ],
                    'fund_flow' => ['nodes', 'edges', 'description'],
                    'withhold_formula' => ['formulas', 'summary'],
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(1, $data['summary']['order_count']);
        $this->assertEquals(598, $data['summary']['total_amount']);
        $this->assertEquals(240, $data['summary']['product_cost']);
        $this->assertEquals(270, $data['summary']['total_cost']);
        $this->assertEquals(328, $data['summary']['total_profit']);
        $this->assertCount(9, $data['fund_flow']['nodes']);
        $this->assertCount(9, $data['withhold_formula']['formulas']);
    }

    public function test_calculate_settlement_api_validation_fails_missing_items()
    {
        $response = $this->api('POST', 'settlements/calculate', [
            'platform_fee' => 10,
        ]);

        $response->assertStatus(422);
    }

    public function test_calculate_settlement_api_validation_fails_invalid_product()
    {
        $response = $this->api('POST', 'settlements/calculate', [
            'items' => [
                ['product_id' => 999999, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_calculate_settlement_api_validation_fails_negative_fee()
    {
        $product = $this->createProduct();
        $response = $this->api('POST', 'settlements/calculate', [
            'platform_fee' => -10,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_calculate_settlement_api_validation_fails_ratio_out_of_range()
    {
        $product = $this->createProduct();
        $response = $this->api('POST', 'settlements/calculate', [
            'supplier_ratio' => 1.5,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(422);
    }

    // ─── POST /settlements ─────────────────────────────────────────────────

    public function test_store_settlement_api_success()
    {
        $product = $this->createProduct();

        $response = $this->api('POST', 'settlements', [
            'type' => Settlement::TYPE_MANUAL,
            'settlement_date' => '2024-06-01',
            'platform_fee' => 15,
            'other_cost' => 5,
            'supplier_ratio' => 0.5,
            'distributor_ratio' => 0.2,
            'platform_ratio' => 0.3,
            'remark' => 'Test settlement',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 3,
                    'sale_price' => 200,
                    'unit_cost' => 80,
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'code', 'message',
                'data' => ['id', 'settlement_no', 'total_amount', 'total_profit'],
            ]);

        $this->assertDatabaseHas('shearerline_settlements', [
            'type' => Settlement::TYPE_MANUAL,
            'remark' => 'Test settlement',
        ]);
    }

    public function test_store_settlement_api_validation_ratios_sum_must_equal_one()
    {
        $product = $this->createProduct();

        $response = $this->api('POST', 'settlements', [
            'supplier_ratio' => 0.5,
            'distributor_ratio' => 0.5,
            'platform_ratio' => 0.5,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_store_settlement_api_validation_invalid_type()
    {
        $response = $this->api('POST', 'settlements', [
            'type' => 'invalid_type',
        ]);

        $response->assertStatus(422);
    }

    // ─── GET /settlements ──────────────────────────────────────────────────

    public function test_index_settlements_success()
    {
        $this->createSettlement();
        $this->createSettlement(Settlement::STATUS_CONFIRMED);

        $response = $this->api('GET', 'settlements');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'code', 'message',
                'data' => [
                    'list',
                    'pagination' => ['total', 'per_page', 'current_page'],
                ],
            ]);

        $this->assertEquals(2, $response->json('data.pagination.total'));
    }

    public function test_index_settlements_filter_by_status()
    {
        $this->createSettlement(Settlement::STATUS_PENDING);
        $this->createSettlement(Settlement::STATUS_CONFIRMED);
        $this->createSettlement(Settlement::STATUS_CONFIRMED);

        $response = $this->api('GET', 'settlements?status=' . Settlement::STATUS_CONFIRMED);

        $this->assertEquals(2, $response->json('data.pagination.total'));
    }

    public function test_index_settlements_filter_by_type()
    {
        $s1 = $this->createSettlement();
        $s1->type = Settlement::TYPE_ORDER;
        $s1->save();

        $this->createSettlement();

        $response = $this->api('GET', 'settlements?type=' . Settlement::TYPE_ORDER);
        $this->assertEquals(1, $response->json('data.pagination.total'));
    }

    // ─── GET /settlements/{id} ─────────────────────────────────────────────

    public function test_show_settlement_success()
    {
        $settlement = $this->createSettlement();

        $response = $this->api('GET', "settlements/{$settlement->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $settlement->id)
            ->assertJsonStructure([
                'data' => ['id', 'settlement_no', 'items', 'fund_flow', 'withhold_formula'],
            ]);
    }

    public function test_show_settlement_not_found_404()
    {
        $response = $this->api('GET', 'settlements/999999');
        $response->assertStatus(404);
    }

    // ─── PUT /settlements/{id} ─────────────────────────────────────────────

    public function test_update_settlement_pending_success()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_PENDING);
        $product = $this->createProduct(['sale_price' => 500]);

        $response = $this->api('PUT', "settlements/{$settlement->id}", [
            'remark' => 'Updated via API',
            'platform_fee' => 30,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'sale_price' => 500, 'unit_cost' => 200],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('shearerline_settlements', [
            'id' => $settlement->id,
            'remark' => 'Updated via API',
            'platform_fee' => 30,
        ]);
    }

    public function test_update_settlement_confirmed_fails_422()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_CONFIRMED);

        $response = $this->api('PUT', "settlements/{$settlement->id}", [
            'remark' => 'Should fail',
        ]);

        $response->assertStatus(422);
    }

    public function test_update_settlement_settled_fails_422()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_SETTLED, false);

        $response = $this->api('PUT', "settlements/{$settlement->id}", [
            'remark' => 'Should fail',
        ]);

        $response->assertStatus(422);
    }

    // ─── POST /settlements/{id}/confirm ────────────────────────────────────

    public function test_confirm_settlement_api_success()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_PENDING);

        $response = $this->api('POST', "settlements/{$settlement->id}/confirm");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', Settlement::STATUS_CONFIRMED);
    }

    public function test_confirm_settlement_already_confirmed_fails()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_CONFIRMED);

        $response = $this->api('POST', "settlements/{$settlement->id}/confirm");
        $response->assertStatus(422);
    }

    // ─── POST /settlements/{id}/settle ─────────────────────────────────────

    public function test_settle_settlement_api_success()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_CONFIRMED);

        $response = $this->api('POST', "settlements/{$settlement->id}/settle");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', Settlement::STATUS_SETTLED);

        $this->assertNotNull($response->json('data.settled_at'));
    }

    public function test_settle_settlement_pending_fails()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_PENDING);

        $response = $this->api('POST', "settlements/{$settlement->id}/settle");
        $response->assertStatus(422);
    }

    // ─── POST /settlements/{id}/cancel ─────────────────────────────────────

    public function test_cancel_settlement_pending_api_success()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_PENDING);

        $response = $this->api('POST', "settlements/{$settlement->id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', Settlement::STATUS_CANCELLED);
    }

    public function test_cancel_settlement_confirmed_api_success()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_CONFIRMED);

        $response = $this->api('POST', "settlements/{$settlement->id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', Settlement::STATUS_CANCELLED);
    }

    public function test_cancel_settlement_settled_fails()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_SETTLED, false);

        $response = $this->api('POST', "settlements/{$settlement->id}/cancel");
        $response->assertStatus(422);
    }

    // ─── DELETE /settlements/{id} ──────────────────────────────────────────

    public function test_destroy_settlement_pending_success()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_PENDING);

        $response = $this->api('DELETE', "settlements/{$settlement->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('shearerline_settlements', ['id' => $settlement->id]);
    }

    public function test_destroy_settlement_confirmed_fails()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_CONFIRMED);

        $response = $this->api('DELETE', "settlements/{$settlement->id}");

        $response->assertStatus(422);
    }

    public function test_destroy_settlement_settled_fails()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_SETTLED, false);

        $response = $this->api('DELETE', "settlements/{$settlement->id}");

        $response->assertStatus(422);
    }
}
