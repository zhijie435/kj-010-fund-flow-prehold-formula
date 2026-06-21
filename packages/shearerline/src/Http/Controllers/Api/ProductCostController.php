<?php

namespace Shearerline\Http\Controllers\Api;

use Shearerline\Http\Controllers\Controller;
use Shearerline\Http\Requests\StoreProductCostRequest;
use Shearerline\Http\Requests\UpdateProductCostRequest;
use Shearerline\Models\Product;
use Shearerline\Models\ProductCost;
use Shearerline\Shearerline;
use Illuminate\Http\Request;

class ProductCostController extends Controller
{
    protected $shearerline;

    public function __construct(Shearerline $shearerline)
    {
        $this->shearerline = $shearerline;
    }

    public function index(Request $request, Product $product)
    {
        $filters = $request->only([
            'cost_type', 'is_active', 'per_page'
        ]);

        $costs = $this->shearerline->getProductCosts($product->id, $filters);

        return $this->paginated($costs);
    }

    public function store(StoreProductCostRequest $request)
    {
        $cost = $this->shearerline->createProductCost($request->validated());

        return $this->success($cost, '成本项创建成功', 201);
    }

    public function show(ProductCost $productCost)
    {
        return $this->success($productCost->load('product'));
    }

    public function update(UpdateProductCostRequest $request, ProductCost $productCost)
    {
        $cost = $this->shearerline->updateProductCost($productCost->id, $request->validated());

        return $this->success($cost, '成本项更新成功');
    }

    public function destroy(ProductCost $productCost)
    {
        $this->shearerline->deleteProductCost($productCost->id);

        return $this->success(null, '成本项删除成功');
    }
}
