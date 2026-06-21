<?php

return [
    'pagination' => [
        'per_page' => 15,
    ],

    'api_route_prefix' => 'api/shearerline',

    'api_middleware' => ['api'],

    'views' => [
        'enabled' => true,
    ],

    'settlement' => [
        'default_supplier_ratio' => 0.50,
        'default_distributor_ratio' => 0.20,
        'default_platform_ratio' => 0.30,

        'grade_discounts' => [
            'normal' => ['name' => '普通批发商', 'discount_rate' => 0.00],
            'silver' => ['name' => '白银批发商', 'discount_rate' => 0.05],
            'gold' => ['name' => '黄金批发商', 'discount_rate' => 0.10],
            'platinum' => ['name' => '铂金批发商', 'discount_rate' => 0.15],
            'diamond' => ['name' => '钻石批发商', 'discount_rate' => 0.20],
        ],

        'default_grade' => 'normal',

        'cost_types' => [
            'purchase' => '采购成本',
            'shipping' => '物流成本',
            'packaging' => '包装成本',
            'platform_fee' => '平台费用',
            'marketing' => '营销成本',
            'tax' => '税费',
            'other' => '其他成本',
        ],

        'settlement_types' => [
            'order' => '按订单结算',
            'monthly' => '月度结算',
            'manual' => '手动结算',
        ],

        'settlement_statuses' => [
            'pending' => '待确认',
            'confirmed' => '已确认',
            'settled' => '已结算',
            'cancelled' => '已取消',
        ],
    ],
];
