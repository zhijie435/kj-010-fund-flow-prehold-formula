<?php

namespace Shearerline\Tests\Unit;

use Shearerline\Exceptions\SettlementStateException;
use Shearerline\Models\Product;
use Shearerline\Models\Settlement;
use Shearerline\StateMachines\SettlementStateMachine;
use Shearerline\Tests\TestCase;

class SettlementStateMachineTest extends TestCase
{
    protected function createSettlement(string $status, bool $withItems = true): Settlement
    {
        $settlement = Settlement::create([
            'settlement_no' => 'STL-SM-' . uniqid(),
            'type' => Settlement::TYPE_MANUAL,
            'settlement_date' => now()->toDateString(),
            'status' => $status,
        ]);

        if ($withItems) {
            $product = Product::create([
                'name' => 'Test',
                'sku' => 'SKU-' . uniqid(),
                'sale_price' => 100,
                'supplier_price' => 50,
                'status' => 1,
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
            $settlement->refresh();
            $settlement->load('items');
        }

        return $settlement;
    }

    // ─── isEditable: 页面编辑状态按钮控制 ─────────────────────────────────

    public function test_pending_status_is_editable()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_PENDING);
        $sm = new SettlementStateMachine($settlement);
        $this->assertTrue($sm->isEditable());
        $this->assertTrue($settlement->isEditable());
    }

    public function test_cancelled_status_is_editable()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_CANCELLED, false);
        $sm = new SettlementStateMachine($settlement);
        $this->assertTrue($sm->isEditable());
    }

    public function test_confirmed_status_is_not_editable()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_CONFIRMED);
        $sm = new SettlementStateMachine($settlement);
        $this->assertFalse($sm->isEditable());
        $this->assertFalse($settlement->isEditable());
    }

    public function test_settled_status_is_not_editable()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_SETTLED, false);
        $sm = new SettlementStateMachine($settlement);
        $this->assertFalse($sm->isEditable());
    }

    // ─── canTransition: 操作按钮可用性 ────────────────────────────────────

    public function test_pending_can_confirm_and_cancel()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_PENDING);
        $sm = new SettlementStateMachine($settlement);

        $this->assertTrue($sm->canTransition('confirm'));
        $this->assertTrue($sm->canTransition('cancel'));
        $this->assertFalse($sm->canTransition('settle'));

        $this->assertTrue($settlement->canConfirm());
        $this->assertTrue($settlement->canCancel());
        $this->assertFalse($settlement->canSettle());
    }

    public function test_confirmed_can_settle_and_cancel()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_CONFIRMED);
        $sm = new SettlementStateMachine($settlement);

        $this->assertFalse($sm->canTransition('confirm'));
        $this->assertTrue($sm->canTransition('settle'));
        $this->assertTrue($sm->canTransition('cancel'));

        $this->assertFalse($settlement->canConfirm());
        $this->assertTrue($settlement->canSettle());
        $this->assertTrue($settlement->canCancel());
    }

    public function test_settled_cannot_do_any_transition()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_SETTLED, false);
        $sm = new SettlementStateMachine($settlement);

        $this->assertFalse($sm->canTransition('confirm'));
        $this->assertFalse($sm->canTransition('settle'));
        $this->assertFalse($sm->canTransition('cancel'));

        $this->assertEmpty($sm->getAvailableTransitions());
    }

    public function test_cancelled_cannot_do_any_transition()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_CANCELLED, false);
        $sm = new SettlementStateMachine($settlement);

        $this->assertEmpty($sm->getAvailableTransitions());
        $this->assertFalse($settlement->canConfirm());
        $this->assertFalse($settlement->canSettle());
        $this->assertFalse($settlement->canCancel());
    }

    public function test_get_available_transitions_returns_correct_list()
    {
        $pending = $this->createSettlement(Settlement::STATUS_PENDING);
        $sm = new SettlementStateMachine($pending);
        $this->assertEqualsCanonicalizing(['confirm', 'cancel'], $sm->getAvailableTransitions());

        $confirmed = $this->createSettlement(Settlement::STATUS_CONFIRMED);
        $sm2 = new SettlementStateMachine($confirmed);
        $this->assertEqualsCanonicalizing(['settle', 'cancel'], $sm2->getAvailableTransitions());
    }

    // ─── transition: confirm ──────────────────────────────────────────────

    public function test_transition_confirm_from_pending_to_confirmed()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_PENDING);
        $sm = new SettlementStateMachine($settlement);

        $result = $sm->transition('confirm');

        $this->assertEquals(Settlement::STATUS_CONFIRMED, $result->status);
    }

    public function test_transition_confirm_without_items_throws_exception()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_PENDING, false);
        $sm = new SettlementStateMachine($settlement);

        $this->expectException(SettlementStateException::class);
        $this->expectExceptionMessage('结算单没有明细项，无法确认');

        $sm->transition('confirm');
    }

    public function test_transition_confirm_recalculates_totals()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_PENDING);
        $settlement->total_amount = 0;
        $settlement->save();

        $sm = new SettlementStateMachine($settlement);
        $result = $sm->transition('confirm');

        $this->assertEquals(100.00, (float) $result->total_amount);
    }

    // ─── transition: settle ───────────────────────────────────────────────

    public function test_transition_settle_from_confirmed_to_settled()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_CONFIRMED);
        $sm = new SettlementStateMachine($settlement);

        $result = $sm->transition('settle');

        $this->assertEquals(Settlement::STATUS_SETTLED, $result->status);
        $this->assertNotNull($result->settled_at);
    }

    // ─── transition: cancel ───────────────────────────────────────────────

    public function test_transition_cancel_from_pending_to_cancelled()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_PENDING);
        $sm = new SettlementStateMachine($settlement);

        $result = $sm->transition('cancel');

        $this->assertEquals(Settlement::STATUS_CANCELLED, $result->status);
    }

    public function test_transition_cancel_from_confirmed_to_cancelled()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_CONFIRMED);
        $sm = new SettlementStateMachine($settlement);

        $result = $sm->transition('cancel');

        $this->assertEquals(Settlement::STATUS_CANCELLED, $result->status);
    }

    // ─── transition: 异常分支 ─────────────────────────────────────────────

    public function test_transition_invalid_from_settled_throws_exception()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_SETTLED, false);
        $sm = new SettlementStateMachine($settlement);

        $this->expectException(SettlementStateException::class);
        $this->expectExceptionMessage('无法执行');

        $sm->transition('cancel');
    }

    public function test_transition_nonexistent_action_throws_exception()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_PENDING);
        $sm = new SettlementStateMachine($settlement);

        $this->expectException(SettlementStateException::class);

        $sm->transition('unknown_action');
    }

    public function test_transition_settle_from_pending_throws_exception()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_PENDING);
        $sm = new SettlementStateMachine($settlement);

        $this->expectException(SettlementStateException::class);
        $sm->transition('settle');
    }

    public function test_transition_confirm_from_confirmed_throws_exception()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_CONFIRMED);
        $sm = new SettlementStateMachine($settlement);

        $this->expectException(SettlementStateException::class);
        $sm->transition('confirm');
    }

    // ─── 状态流转完整链路 ──────────────────────────────────────────────────

    public function test_full_lifecycle_pending_confirmed_settled()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_PENDING);

        $sm1 = new SettlementStateMachine($settlement);
        $settlement = $sm1->transition('confirm');
        $this->assertEquals(Settlement::STATUS_CONFIRMED, $settlement->status);

        $sm2 = new SettlementStateMachine($settlement);
        $settlement = $sm2->transition('settle');
        $this->assertEquals(Settlement::STATUS_SETTLED, $settlement->status);
    }

    public function test_pending_then_cancelled_then_nothing()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_PENDING);

        $sm = new SettlementStateMachine($settlement);
        $settlement = $sm->transition('cancel');
        $this->assertEquals(Settlement::STATUS_CANCELLED, $settlement->status);

        $sm2 = new SettlementStateMachine($settlement);
        $this->assertEmpty($sm2->getAvailableTransitions());
    }

    public function test_confirmed_then_cancelled()
    {
        $settlement = $this->createSettlement(Settlement::STATUS_CONFIRMED);

        $sm = new SettlementStateMachine($settlement);
        $settlement = $sm->transition('cancel');
        $this->assertEquals(Settlement::STATUS_CANCELLED, $settlement->status);
    }

    // ─── 页面状态标签 ──────────────────────────────────────────────────────

    public function test_status_names_match_labels_for_ui()
    {
        $pending = $this->createSettlement(Settlement::STATUS_PENDING);
        $this->assertEquals('待确认', $pending->status_name);

        $confirmed = $this->createSettlement(Settlement::STATUS_CONFIRMED);
        $this->assertEquals('已确认', $confirmed->status_name);

        $settled = $this->createSettlement(Settlement::STATUS_SETTLED, false);
        $this->assertEquals('已结算', $settled->status_name);

        $cancelled = $this->createSettlement(Settlement::STATUS_CANCELLED, false);
        $this->assertEquals('已取消', $cancelled->status_name);
    }

    public function test_type_names_match_labels_for_ui()
    {
        $s1 = $this->createSettlement(Settlement::STATUS_PENDING);
        $s1->type = Settlement::TYPE_ORDER;
        $this->assertEquals('按订单结算', $s1->type_name);

        $s2 = $this->createSettlement(Settlement::STATUS_PENDING, false);
        $s2->type = Settlement::TYPE_MONTHLY;
        $this->assertEquals('月度结算', $s2->type_name);

        $s3 = $this->createSettlement(Settlement::STATUS_PENDING, false);
        $s3->type = Settlement::TYPE_MANUAL;
        $this->assertEquals('手动结算', $s3->type_name);
    }
}
