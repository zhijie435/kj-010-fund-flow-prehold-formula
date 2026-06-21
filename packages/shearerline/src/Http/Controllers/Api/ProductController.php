<?php

namespace Shearerline\Http\Controllers\Api;

use Shearerline\Http\Controllers\Controller;
use Shearerline\Http\Requests\StoreProductRequest;
use Shearerline\Http\Requests\UpdateProductRequest;
use Shearerline\Models\Product;
use Shearerline\Shearerline;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    protected $shearerline;

    public function __construct(Shearerline $shearerline)
    {
        $this->shearerline = $shearerline;
    }

    public function index(Request $request)
    {
        $filters = $request->only([
            'keyword', 'supplier_id', 'category', 'status', 'per_page'
        ]);

        $products = $this->shearerline->getProducts($filters);

        return $this->paginated($products);
    }

    public function all()
    {
        $products = Product::active()
            ->select('id', 'name', 'sku', 'sale_price', 'supplier_price')
            ->orderBy('name')
            ->get();

        return $this->success($products);
    }

    public function store(StoreProductRequest $request)
    {
        $product = $this->shearerline->createProduct($request->validated());

        return $this->success($product, '商品创建成功', 201);
    }

    public function show(Product $product)
    {
        $product = $this->shearerline->getProduct($product->id);

        return $this->success($product);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $product = $this->shearerline->updateProduct($product->id, $request->validated());

        return $this->success($product, '商品更新成功');
    }

    public function destroy(Product $product)
    {
        $this->shearerline->deleteProduct($product->id);

        return $this->success(null, '商品删除成功');
    }

    public function calculateCost(Request $request, Product $product)
    {
        $date = $request->input('date');
        $result = $this->shearerline->calculateProductCost($product->id, $date);

        return $this->success($result, '成本计算成功');
    }

    public function batchCalculateCost(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'integer|exists:shearerline_products,id',
            'date' => 'nullable|date',
        ]);

        $result = $this->shearerline->calculateMultipleProductsCost(
            $request->input('product_ids'),
            $request->input('date')
        );

        return $this->success($result, '批量成本计算成功');
    }

    public function costTypes()
    {
        return $this->success($this->shearerline->getCostTypes());
    }

    public function gradeDiscounts()
    {
        return $this->success($this->shearerline->getGradeDiscounts());
    }

    public function calculateCostByGrade(Request $request, Product $product)
    {
        $grade = $request->input('grade');
        $result = $this->shearerline->calculateProductCostByGrade($product->id, $grade);

        return $this->success($result, '等级折扣成本计算成功');
    }

    public function batchCalculateCostByGrade(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'integer|exists:shearerline_products,id',
            'grade' => 'nullable|string',
        ]);

        $result = $this->shearerline->calculateMultipleProductsCostByGrade(
            $request->input('product_ids'),
            $request->input('grade')
        );

        return $this->success($result, '批量等级折扣成本计算成功');
    }

    public function calculateIncreasedCost(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|integer|exists:shearerline_products,id',
            'items.*.quantity' => 'nullable|integer|min:1',
            'items.*.supplier_price' => 'nullable|numeric|min:0',
            'grade' => 'nullable|string',
        ]);

        $result = $this->shearerline->calculateIncreasedProductCost(
            $request->input('items'),
            $request->input('grade')
        );

        return $this->success($result, '增加商品成本计算成功');
    }
}
