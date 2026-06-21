<?php

return [
    'products' => [
        'title' => '商品管理',
        'created' => '商品创建成功',
        'updated' => '商品更新成功',
        'deleted' => '商品删除成功',
    ],
    'product_costs' => [
        'title' => '成本管理',
        'created' => '成本项创建成功',
        'updated' => '成本项更新成功',
        'deleted' => '成本项删除成功',
    ],
    'settlements' => [
        'title' => '结算管理',
        'created' => '结算单创建成功',
        'updated' => '结算单更新成功',
        'confirmed' => '结算单确认成功',
        'settled' => '结算单结算完成',
        'cancelled' => '结算单已取消',
        'errors' => [
            'not_editable' => '结算单当前状态不可编辑',
            'not_confirmable' => '结算单当前状态不可确认',
            'not_settleable' => '结算单当前状态不可结算',
            'not_cancelable' => '结算单当前状态不可取消',
            'no_items' => '结算单没有明细项，无法确认',
        ],
    ],
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
];
