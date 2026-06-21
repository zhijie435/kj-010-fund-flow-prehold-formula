<?php

namespace Shearerline\StateMachines;

use Shearerline\Models\Settlement;
use Shearerline\Exceptions\SettlementStateException;
use Illuminate\Support\Facades\Auth;

class SettlementStateMachine
{
    protected $settlement;

    protected $transitions = [
        Settlement::STATUS_PENDING => [
            'confirm' => Settlement::STATUS_CONFIRMED,
            'cancel' => Settlement::STATUS_CANCELLED,
        ],
        Settlement::STATUS_CONFIRMED => [
            'settle' => Settlement::STATUS_SETTLED,
            'cancel' => Settlement::STATUS_CANCELLED,
        ],
        Settlement::STATUS_SETTLED => [],
        Settlement::STATUS_CANCELLED => [],
    ];

    protected $stateLabels = [];

    public function __construct(Settlement $settlement)
    {
        $this->settlement = $settlement;
        $this->stateLabels = get_settlement_statuses();
    }

    public function canTransition(string $transition): bool
    {
        $currentStatus = $this->settlement->status;
        return isset($this->transitions[$currentStatus][$transition]);
    }

    public function getAvailableTransitions(): array
    {
        $currentStatus = $this->settlement->status;
        return array_keys($this->transitions[$currentStatus] ?? []);
    }

    public function transition(string $transition, array $extraData = []): Settlement
    {
        if (!$this->canTransition($transition)) {
            throw new SettlementStateException(
                sprintf(
                    '结算单当前状态为「%s」，无法执行「%s」操作',
                    $this->stateLabels[$this->settlement->status] ?? $this->settlement->status,
                    $this->getTransitionLabel($transition)
                )
            );
        }

        $method = 'apply' . ucfirst($transition);

        if (method_exists($this, $method)) {
            return $this->$method($extraData);
        }

        $targetStatus = $this->transitions[$this->settlement->status][$transition];
        $this->settlement->status = $targetStatus;

        if (Auth::check()) {
            $this->settlement->updated_by = Auth::id();
        }

        $this->settlement->save();

        return $this->settlement->fresh('items');
    }

    protected function applyConfirm(array $extraData): Settlement
    {
        if ($this->settlement->items->count() === 0) {
            throw new SettlementStateException('结算单没有明细项，无法确认');
        }

        $this->settlement->recalculateTotals();

        $data = [
            'status' => Settlement::STATUS_CONFIRMED,
        ];

        if (Auth::check()) {
            $data['updated_by'] = Auth::id();
        }

        $this->settlement->fill($data);
        $this->settlement->save();

        return $this->settlement->fresh('items');
    }

    protected function applySettle(array $extraData): Settlement
    {
        $this->settlement->recalculateTotals();

        $data = [
            'status' => Settlement::STATUS_SETTLED,
            'settled_at' => now(),
        ];

        if (Auth::check()) {
            $data['settled_by'] = Auth::id();
            $data['updated_by'] = Auth::id();
        }

        $this->settlement->fill($data);
        $this->settlement->save();

        return $this->settlement->fresh('items');
    }

    protected function applyCancel(array $extraData): Settlement
    {
        $data = [
            'status' => Settlement::STATUS_CANCELLED,
        ];

        if (Auth::check()) {
            $data['updated_by'] = Auth::id();
        }

        $this->settlement->update($data);

        return $this->settlement->fresh('items');
    }

    public function isEditable(): bool
    {
        return in_array(
            $this->settlement->status,
            [Settlement::STATUS_PENDING, Settlement::STATUS_CANCELLED]
        );
    }

    public function canConfirm(): bool
    {
        return $this->canTransition('confirm');
    }

    public function canSettle(): bool
    {
        return $this->canTransition('settle');
    }

    public function canCancel(): bool
    {
        return $this->canTransition('cancel');
    }

    protected function getTransitionLabel(string $transition): string
    {
        $labels = [
            'confirm' => '确认',
            'settle' => '结算',
            'cancel' => '取消',
        ];

        return $labels[$transition] ?? $transition;
    }
}
