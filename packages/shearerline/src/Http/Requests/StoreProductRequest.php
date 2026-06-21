<?php

namespace Shearerline\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:shearerline_products,sku',
            'barcode' => 'nullable|string|max:100',
            'supplier_id' => 'nullable|integer',
            'category' => 'nullable|string|max:100',
            'unit' => 'nullable|string|max:50',
            'sale_price' => 'required|numeric|min:0',
            'supplier_price' => 'nullable|numeric|min:0',
            'weight' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string|max:500',
            'stock' => 'nullable|integer|min:0',
            'warning_stock' => 'nullable|integer|min:0',
            'status' => 'nullable|integer|in:0,1',
        ];
    }
}
