<?php

namespace Shearerline\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

interface ShearerlineInterface
{
    public function getProducts(array $filters = []): LengthAwarePaginator;

    public function getProduct(int $id);

    public function createProduct(array $data);

    public function updateProduct(int $id, array $data);

    public function deleteProduct(int $id): bool;

    public function getProductCosts(int $productId, array $filters = []): LengthAwarePaginator;

    public function createProductCost(array $data);

    public function updateProductCost(int $id, array $data);

    public function deleteProductCost(int $id): bool;

    public function calculateProductCost(int $productId, ?string $date = null): array;

    public function getSettlements(array $filters = []): LengthAwarePaginator;

    public function getSettlement(int $id);

    public function createSettlement(array $data);

    public function updateSettlement(int $id, array $data);

    public function confirmSettlement(int $id);

    public function settleSettlement(int $id);

    public function cancelSettlement(int $id);

    public function calculateSettlement(array $items, array $ratios = []): array;
}
