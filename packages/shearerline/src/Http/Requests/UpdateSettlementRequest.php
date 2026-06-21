<?php

namespace Shearerline\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'sometimes|string|in:order,monthly,manual',
            'settlement_date' => 'sometimes|date',
            'order_no' => 'nullable|string|max:100',
            'platform_fee' => 'nullable|numeric|min:0',
            'other_cost' => 'nullable|numeric|min:0',
            'supplier_ratio' => 'nullable|numeric|min:0|max:1',
            'distributor_ratio' => 'nullable|numeric|min:0|max:1',
            'platform_ratio' => 'nullable|numeric|min:0|max:1',
            'remark' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.product_id' => 'required_with:items|integer|exists:shearerline_products,id',
            'items.*.quantity' => 'nullable|integer|min:1',
            'items.*.sale_price' => 'nullable|numeric|min:0',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
        ];
    }
}
