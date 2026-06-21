<?php

namespace Shearerline\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalculateSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settlement_date' => 'nullable|date',
            'platform_fee' => 'nullable|numeric|min:0',
            'other_cost' => 'nullable|numeric|min:0',
            'supplier_ratio' => 'nullable|numeric|min:0|max:1',
            'distributor_ratio' => 'nullable|numeric|min:0|max:1',
            'platform_ratio' => 'nullable|numeric|min:0|max:1',
            'items' => 'required|array',
            'items.*.product_id' => 'required|integer|exists:shearerline_products,id',
            'items.*.quantity' => 'nullable|integer|min:1',
            'items.*.sale_price' => 'nullable|numeric|min:0',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
        ];
    }
}
