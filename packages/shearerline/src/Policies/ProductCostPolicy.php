<?php

namespace Shearerline\Policies;

use Shearerline\Models\ProductCost;
use App\Models\User;

class ProductCostPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, ProductCost $productCost): bool
    {
        return true;
    }

    public function create(?User $user): bool
    {
        return true;
    }

    public function update(?User $user, ProductCost $productCost): bool
    {
        return true;
    }

    public function delete(?User $user, ProductCost $productCost): bool
    {
        return true;
    }
}
