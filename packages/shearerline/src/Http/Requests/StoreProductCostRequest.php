<?php

namespace Shearerline\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|integer|exists:shearerline_products,id',
            'cost_type' => 'required|string|in:purchase,shipping,packaging,platform_fee,marketing,tax,other',
            'cost_name' => 'required|string|max:255',
            'unit_cost' => 'required|numeric|min:0',
            'quantity' => 'nullable|integer|min:1',
            'effective_date' => 'required|date',
            'expiry_date' => 'nullable|date|after:effective_date',
            'is_active' => 'nullable|integer|in:0,1',
            'remark' => 'nullable|string',
        ];
    }
}
