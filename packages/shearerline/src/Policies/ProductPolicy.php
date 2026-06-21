<?php

namespace Shearerline\Policies;

use Shearerline\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProductPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Product $product): bool
    {
        return true;
    }

    public function create(?User $user): bool
    {
        return true;
    }

    public function update(?User $user, Product $product): bool
    {
        return true;
    }

    public function delete(?User $user, Product $product): bool
    {
        return true;
    }
}
