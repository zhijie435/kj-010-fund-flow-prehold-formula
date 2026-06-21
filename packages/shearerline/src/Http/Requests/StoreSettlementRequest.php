<?php

namespace Shearerline\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settlement_no' => 'nullable|string|max:50|unique:shearerline_settlements,settlement_no',
            'type' => 'nullable|string|in:order,monthly,manual',
            'settlement_date' => 'nullable|date',
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

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $supplierRatio = (float) ($this->input('supplier_ratio', 0));
            $distributorRatio = (float) ($this->input('distributor_ratio', 0));
            $platformRatio = (float) ($this->input('platform_ratio', 0));

            $totalRatio = $supplierRatio + $distributorRatio + $platformRatio;

            if ($this->filled(['supplier_ratio', 'distributor_ratio', 'platform_ratio']) && abs($totalRatio - 1) > 0.0001) {
                $validator->errors()->add('ratios', '分成比例之和必须等于1 (当前: ' . number_format($totalRatio, 4) . ')');
            }
        });
    }
}
