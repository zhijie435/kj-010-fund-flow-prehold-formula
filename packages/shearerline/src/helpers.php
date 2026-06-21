<?php

if (!function_exists('format_money')) {
    function format_money($amount, int $decimals = 2): string
    {
        return number_format((float) $amount, $decimals, '.', '');
    }
}

if (!function_exists('calculate_profit_rate')) {
    function calculate_profit_rate($revenue, $cost, int $decimals = 4): float
    {
        if ((float) $revenue <= 0) {
            return 0;
        }
        return round(((float) $revenue - (float) $cost) / (float) $revenue, $decimals);
    }
}

if (!function_exists('generate_settlement_no')) {
    function generate_settlement_no(): string
    {
        return 'STL' . date('YmdHis') . str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('get_cost_types')) {
    function get_cost_types(): array
    {
        return config('shearerline.settlement.cost_types', [
            'purchase' => '采购成本',
            'shipping' => '物流成本',
            'packaging' => '包装成本',
            'platform_fee' => '平台费用',
            'marketing' => '营销成本',
            'tax' => '税费',
            'other' => '其他成本',
        ]);
    }
}

if (!function_exists('get_settlement_types')) {
    function get_settlement_types(): array
    {
        return config('shearerline.settlement.settlement_types', [
            'order' => '按订单结算',
            'monthly' => '月度结算',
            'manual' => '手动结算',
        ]);
    }
}

if (!function_exists('get_settlement_statuses')) {
    function get_settlement_statuses(): array
    {
        return config('shearerline.settlement.settlement_statuses', [
            'pending' => '待确认',
            'confirmed' => '已确认',
            'settled' => '已结算',
            'cancelled' => '已取消',
        ]);
    }
}

if (!function_exists('get_grade_discounts')) {
    function get_grade_discounts(): array
    {
        return config('shearerline.settlement.grade_discounts', [
            'normal' => ['name' => '普通等级', 'discount_rate' => 0.00],
            'silver' => ['name' => '白银等级', 'discount_rate' => 0.05],
            'gold' => ['name' => '黄金等级', 'discount_rate' => 0.10],
            'platinum' => ['name' => '铂金等级', 'discount_rate' => 0.15],
            'diamond' => ['name' => '钻石等级', 'discount_rate' => 0.20],
        ]);
    }
}

if (!function_exists('get_grade_discount_rate')) {
    function get_grade_discount_rate(string $grade): float
    {
        $grades = get_grade_discounts();
        return isset($grades[$grade]) ? (float) $grades[$grade]['discount_rate'] : 0.0;
    }
}

if (!function_exists('get_default_grade')) {
    function get_default_grade(): string
    {
        return config('shearerline.settlement.default_grade', 'normal');
    }
}
