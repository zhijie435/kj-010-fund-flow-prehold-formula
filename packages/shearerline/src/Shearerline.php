<?php

namespace Shearerline;

use Shearerline\Contracts\ShearerlineInterface;
use Shearerline\Models\Product;
use Shearerline\Models\ProductCost;
use Shearerline\Models\Settlement;
use Shearerline\Models\SettlementItem;
use Shearerline\Services\CostCalculationService;
use Shearerline\Services\SettlementService;
use Illuminate\Support\Facades\Config;
use Illuminate\Pagination\LengthAwarePaginator;

class Shearerline implements ShearerlineInterface
{
    protected $perPage;
    protected $costService;
    protected $settlementService;

    public function __construct(
        ?CostCalculationService $costService = null,
        ?SettlementService $settlementService = null
    ) {
        $this->perPage = Config::get('shearerline.pagination.per_page', 15);
        $this->costService = $costService ?? new CostCalculationService();
        $this->settlementService = $settlementService ?? new SettlementService();
    }

    public function getProducts(array $filters = []): LengthAwarePaginator
    {
        $query = Product::query();

        if (!empty($filters['keyword'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['keyword']}%")
                  ->orWhere('sku', 'like', "%{$filters['keyword']}%")
                  ->orWhere('barcode', 'like', "%{$filters['keyword']}%");
            });
        }

        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['status']) || isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->with(['activeCosts'])
            ->latest()
            ->paginate($filters['per_page'] ?? $this->perPage);
    }

    public function getProduct(int $id)
    {
        return Product::with(['costs'])
            ->findOrFail($id);
    }

    public function createProduct(array $data)
    {
        return Product::create($data);
    }

    public function updateProduct(int $id, array $data)
    {
        $product = Product::findOrFail($id);
        $product->update($data);
        return $product->fresh();
    }

    public function deleteProduct(int $id): bool
    {
        return Product::findOrFail($id)->delete();
    }

    public function getProductCosts(int $productId, array $filters = []): LengthAwarePaginator
    {
        $query = ProductCost::where('product_id', $productId);

        if (!empty($filters['cost_type'])) {
            $query->where('cost_type', $filters['cost_type']);
        }

        if (!empty($filters['is_active']) || isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->latest('effective_date')
            ->paginate($filters['per_page'] ?? $this->perPage);
    }

    public function createProductCost(array $data)
    {
        return ProductCost::create($data);
    }

    public function updateProductCost(int $id, array $data)
    {
        $cost = ProductCost::findOrFail($id);
        $cost->update($data);
        return $cost->fresh();
    }

    public function deleteProductCost(int $id): bool
    {
        return ProductCost::findOrFail($id)->delete();
    }

    public function calculateProductCost(int $productId, ?string $date = null): array
    {
        $product = Product::findOrFail($productId);
        return $this->costService->calculateProductCost($product, $date);
    }

    public function calculateMultipleProductsCost(array $productIds, ?string $date = null): array
    {
        return $this->costService->calculateMultipleProductsCost($productIds, $date);
    }

    public function getSettlements(array $filters = []): LengthAwarePaginator
    {
        $query = Settlement::query();

        if (!empty($filters['keyword'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('settlement_no', 'like', "%{$filters['keyword']}%")
                  ->orWhere('order_no', 'like', "%{$filters['keyword']}%");
            });
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->betweenDates($filters['start_date'], $filters['end_date']);
        }

        return $query->withCount('items')
            ->latest('settlement_date')
            ->paginate($filters['per_page'] ?? $this->perPage);
    }

    public function getSettlement(int $id)
    {
        return Settlement::with(['items'])->findOrFail($id);
    }

    public function createSettlement(array $data)
    {
        return $this->settlementService->createSettlement($data);
    }

    public function updateSettlement(int $id, array $data)
    {
        return $this->settlementService->updateSettlement($id, $data);
    }

    public function confirmSettlement(int $id)
    {
        return $this->settlementService->confirmSettlement($id);
    }

    public function settleSettlement(int $id)
    {
        return $this->settlementService->settleSettlement($id);
    }

    public function cancelSettlement(int $id)
    {
        return $this->settlementService->cancelSettlement($id);
    }

    public function calculateSettlement(array $items, array $ratios = []): array
    {
        return $this->settlementService->calculateSettlement($items, $ratios);
    }

    public function getCostTypes(): array
    {
        return get_cost_types();
    }

    public function getSettlementTypes(): array
    {
        return get_settlement_types();
    }

    public function getSettlementStatuses(): array
    {
        return get_settlement_statuses();
    }

    public function getDashboardStatistics(): array
    {
        $totalProducts = Product::count();
        $activeProducts = Product::active()->count();
        $totalSettlements = Settlement::count();
        $settledSettlements = Settlement::settled()->count();
        $pendingSettlements = Settlement::pending()->count();

        $totalAmount = Settlement::settled()->sum('total_amount');
        $totalProfit = Settlement::settled()->sum('total_profit');
        $totalSupplierShare = Settlement::settled()->sum('supplier_share');
        $totalDistributorShare = Settlement::settled()->sum('distributor_share');
        $totalPlatformShare = Settlement::settled()->sum('platform_share');

        $thisMonthStart = now()->startOfMonth()->toDateString();
        $thisMonthEnd = now()->endOfMonth()->toDateString();

        $monthlySettlements = Settlement::settled()
            ->betweenDates($thisMonthStart, $thisMonthEnd)
            ->count();

        $monthlyAmount = Settlement::settled()
            ->betweenDates($thisMonthStart, $thisMonthEnd)
            ->sum('total_amount');

        $monthlyProfit = Settlement::settled()
            ->betweenDates($thisMonthStart, $thisMonthEnd)
            ->sum('total_profit');

        return [
            'products' => [
                'total' => $totalProducts,
                'active' => $activeProducts,
            ],
            'settlements' => [
                'total' => $totalSettlements,
                'settled' => $settledSettlements,
                'pending' => $pendingSettlements,
                'this_month_count' => $monthlySettlements,
            ],
            'finance' => [
                'total_amount' => round((float) $totalAmount, 2),
                'total_profit' => round((float) $totalProfit, 2),
                'total_supplier_share' => round((float) $totalSupplierShare, 2),
                'total_distributor_share' => round((float) $totalDistributorShare, 2),
                'total_platform_share' => round((float) $totalPlatformShare, 2),
                'this_month_amount' => round((float) $monthlyAmount, 2),
                'this_month_profit' => round((float) $monthlyProfit, 2),
            ],
        ];
    }
}
