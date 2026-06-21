<?php

namespace Shearerline\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cost_type' => 'sometimes|string|in:purchase,shipping,packaging,platform_fee,marketing,tax,other',
            'cost_name' => 'sometimes|string|max:255',
            'unit_cost' => 'sometimes|numeric|min:0',
            'quantity' => 'sometimes|integer|min:1',
            'effective_date' => 'sometimes|date',
            'expiry_date' => 'nullable|date|after:effective_date',
            'is_active' => 'nullable|integer|in:0,1',
            'remark' => 'nullable|string',
        ];
    }
}
