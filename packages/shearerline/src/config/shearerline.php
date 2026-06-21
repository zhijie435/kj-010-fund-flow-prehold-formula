<?php

return [
    'pagination' => [
        'per_page' => env('SHEARERLINE_PER_PAGE', 15),
    ],

    'api_route_prefix' => env('SHEARERLINE_API_PREFIX', 'api/shearerline'),

    'api_middleware' => ['api'],

    'views' => [
        'enabled' => env('SHEARERLINE_VIEWS_ENABLED', true),
    ],

    'queue' => [
        'connection' => env('SHEARERLINE_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),
        'settlement_queue' => env('SHEARERLINE_SETTLEMENT_QUEUE', 'shearerline-settlements'),
        'report_queue' => env('SHEARERLINE_REPORT_QUEUE', 'shearerline-reports'),
        'settlement_async' => env('SHEARERLINE_SETTLEMENT_ASYNC', false),
    ],

    'append' => [
        'fund_flow' => env('SHEARERLINE_APPEND_FUND_FLOW', true),
        'withhold_formula' => env('SHEARERLINE_APPEND_WITHHOLD_FORMULA', true),
    ],

    'settlement' => [
        'default_supplier_ratio' => env('SHEARERLINE_DEFAULT_SUPPLIER_RATIO', 0.50),
        'default_distributor_ratio' => env('SHEARERLINE_DEFAULT_DISTRIBUTOR_RATIO', 0.20),
        'default_platform_ratio' => env('SHEARERLINE_DEFAULT_PLATFORM_RATIO', 0.30),

        'grade_discounts' => [
            'normal' => ['name' => '普通批发商', 'discount_rate' => 0.00],
            'silver' => ['name' => '白银批发商', 'discount_rate' => 0.05],
            'gold' => ['name' => '黄金批发商', 'discount_rate' => 0.10],
            'platinum' => ['name' => '铂金批发商', 'discount_rate' => 0.15],
            'diamond' => ['name' => '钻石批发商', 'discount_rate' => 0.20],
        ],

        'default_grade' => env('SHEARERLINE_DEFAULT_GRADE', 'normal'),

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

    'shipping' => [
        'default_template' => env('SHEARERLINE_SHIPPING_TEMPLATE', 'sf_standard'),
        'volumetric_divisor' => env('SHEARERLINE_VOLUMETRIC_DIVISOR', 6000),
        'weight_unit' => 'kg',
        'dimension_unit' => 'cm',
        'additional_weight_round' => 'ceil',

        'templates' => [
            'sf_standard' => [
                'name' => '顺丰标快',
                'volumetric_divisor' => 6000,
                'first_weight' => 1.0,
                'zones' => [
                    'local' => [
                        'name' => '同城',
                        'first_weight_fee' => 12.00,
                        'additional_weight_fee' => 2.00,
                    ],
                    'province' => [
                        'name' => '省内',
                        'first_weight_fee' => 14.00,
                        'additional_weight_fee' => 3.00,
                    ],
                    'region_1' => [
                        'name' => '一区(江浙沪皖)',
                        'first_weight_fee' => 18.00,
                        'additional_weight_fee' => 5.00,
                    ],
                    'region_2' => [
                        'name' => '二区(京津冀鲁豫等)',
                        'first_weight_fee' => 21.00,
                        'additional_weight_fee' => 7.00,
                    ],
                    'region_3' => [
                        'name' => '三区(东北西北西南等)',
                        'first_weight_fee' => 26.00,
                        'additional_weight_fee' => 10.00,
                    ],
                ],
            ],
            'sf_economy' => [
                'name' => '顺丰特惠',
                'volumetric_divisor' => 8000,
                'first_weight' => 1.0,
                'zones' => [
                    'local' => [
                        'name' => '同城',
                        'first_weight_fee' => 8.00,
                        'additional_weight_fee' => 1.50,
                    ],
                    'province' => [
                        'name' => '省内',
                        'first_weight_fee' => 10.00,
                        'additional_weight_fee' => 2.00,
                    ],
                    'region_1' => [
                        'name' => '一区(江浙沪皖)',
                        'first_weight_fee' => 13.00,
                        'additional_weight_fee' => 3.50,
                    ],
                    'region_2' => [
                        'name' => '二区(京津冀鲁豫等)',
                        'first_weight_fee' => 16.00,
                        'additional_weight_fee' => 5.00,
                    ],
                    'region_3' => [
                        'name' => '三区(东北西北西南等)',
                        'first_weight_fee' => 20.00,
                        'additional_weight_fee' => 7.00,
                    ],
                ],
            ],
            'zt_express' => [
                'name' => '中通快递',
                'volumetric_divisor' => 6000,
                'first_weight' => 1.0,
                'zones' => [
                    'local' => [
                        'name' => '同城',
                        'first_weight_fee' => 6.00,
                        'additional_weight_fee' => 1.00,
                    ],
                    'province' => [
                        'name' => '省内',
                        'first_weight_fee' => 8.00,
                        'additional_weight_fee' => 2.00,
                    ],
                    'region_1' => [
                        'name' => '一区(江浙沪皖)',
                        'first_weight_fee' => 10.00,
                        'additional_weight_fee' => 3.00,
                    ],
                    'region_2' => [
                        'name' => '二区(京津冀鲁豫等)',
                        'first_weight_fee' => 12.00,
                        'additional_weight_fee' => 5.00,
                    ],
                    'region_3' => [
                        'name' => '三区(东北西北西南等)',
                        'first_weight_fee' => 15.00,
                        'additional_weight_fee' => 8.00,
                    ],
                ],
            ],
            'yt_express' => [
                'name' => '圆通速递',
                'volumetric_divisor' => 6000,
                'first_weight' => 1.0,
                'zones' => [
                    'local' => [
                        'name' => '同城',
                        'first_weight_fee' => 6.00,
                        'additional_weight_fee' => 1.00,
                    ],
                    'province' => [
                        'name' => '省内',
                        'first_weight_fee' => 7.00,
                        'additional_weight_fee' => 2.00,
                    ],
                    'region_1' => [
                        'name' => '一区(江浙沪皖)',
                        'first_weight_fee' => 9.00,
                        'additional_weight_fee' => 3.00,
                    ],
                    'region_2' => [
                        'name' => '二区(京津冀鲁豫等)',
                        'first_weight_fee' => 11.00,
                        'additional_weight_fee' => 5.00,
                    ],
                    'region_3' => [
                        'name' => '三区(东北西北西南等)',
                        'first_weight_fee' => 14.00,
                        'additional_weight_fee' => 8.00,
                    ],
                ],
            ],
        ],
    ],
];
