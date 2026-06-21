<?php

namespace Shearerline\Policies;

use Shearerline\Models\Settlement;
use Shearerline\StateMachines\SettlementStateMachine;
use App\Models\User;

class SettlementPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Settlement $settlement): bool
    {
        return true;
    }

    public function create(?User $user): bool
    {
        return $user !== null;
    }

    public function update(?User $user, Settlement $settlement): bool
    {
        if ($user === null) {
            return false;
        }

        $stateMachine = new SettlementStateMachine($settlement);
        return $stateMachine->isEditable();
    }

    public function delete(?User $user, Settlement $settlement): bool
    {
        if ($user === null) {
            return false;
        }

        $stateMachine = new SettlementStateMachine($settlement);
        return $stateMachine->isEditable();
    }

    public function confirm(?User $user, Settlement $settlement): bool
    {
        if ($user === null) {
            return false;
        }

        $stateMachine = new SettlementStateMachine($settlement);
        return $stateMachine->canConfirm();
    }

    public function settle(?User $user, Settlement $settlement): bool
    {
        if ($user === null) {
            return false;
        }

        $stateMachine = new SettlementStateMachine($settlement);
        return $stateMachine->canSettle();
    }

    public function cancel(?User $user, Settlement $settlement): bool
    {
        if ($user === null) {
            return false;
        }

        $stateMachine = new SettlementStateMachine($settlement);
        return $stateMachine->canCancel();
    }
}
