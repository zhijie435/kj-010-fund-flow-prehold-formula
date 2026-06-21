<?php

namespace Shearerline\Policies;

use Shearerline\Models\Settlement;
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
        return true;
    }

    public function update(?User $user, Settlement $settlement): bool
    {
        return $settlement->isEditable();
    }

    public function delete(?User $user, Settlement $settlement): bool
    {
        return $settlement->isEditable();
    }

    public function confirm(?User $user, Settlement $settlement): bool
    {
        return $settlement->canConfirm();
    }

    public function settle(?User $user, Settlement $settlement): bool
    {
        return $settlement->canSettle();
    }

    public function cancel(?User $user, Settlement $settlement): bool
    {
        return $settlement->canCancel();
    }
}
