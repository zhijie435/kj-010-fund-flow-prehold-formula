<?php

namespace Shearerline\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Settlement extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'shearerline_settlements';

    const TYPE_ORDER = 'order';
    const TYPE_MONTHLY = 'monthly';
    const TYPE_MANUAL = 'manual';

    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_SETTLED = 'settled';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'settlement_no',
        'type',
        'settlement_date',
        'order_no',
        'order_count',
        'total_amount',
        'product_cost',
        'platform_fee',
        'other_cost',
        'total_cost',
        'total_profit',
        'profit_rate',
        'supplier_ratio',
        'distributor_ratio',
        'platform_ratio',
        'supplier_share',
        'distributor_share',
        'platform_share',
        'status',
        'remark',
        'created_by',
        'updated_by',
        'settled_by',
        'settled_at',
    ];

    protected $appends = [
        'product_cost_breakdown',
        'fund_flow',
        'withhold_formula',
    ];

    protected function casts(): array
    {
        return [
            'settlement_date' => 'date',
            'order_count' => 'integer',
            'total_amount' => 'decimal:2',
            'product_cost' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'other_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'total_profit' => 'decimal:2',
            'profit_rate' => 'decimal:4',
            'supplier_ratio' => 'decimal:4',
            'distributor_ratio' => 'decimal:4',
            'platform_ratio' => 'decimal:4',
            'supplier_share' => 'decimal:2',
            'distributor_share' => 'decimal:2',
            'platform_share' => 'decimal:2',
            'settled_at' => 'datetime',
        ];
    }

    public function items()
    {
        return $this->hasMany(SettlementItem::class);
    }

    public function getTypeNameAttribute()
    {
        $types = get_settlement_types();
        return $types[$this->type] ?? $this->type;
    }

    public function getStatusNameAttribute()
    {
        $statuses = get_settlement_statuses();
        return $statuses[$this->status] ?? $this->status;
    }

    public function getProductCostBreakdownAttribute()
    {
        if (!$this->relationLoaded('items')) {
            return [];
        }

        $breakdown = [];
        foreach ($this->items as $item) {
            if (!empty($item->cost_breakdown)) {
                foreach ($item->cost_breakdown as $bd) {
                    $type = $bd['cost_type'];
                    if (!isset($breakdown[$type])) {
                        $breakdown[$type] = [
                            'cost_type' => $type,
                            'cost_type_name' => $bd['cost_type_name'],
                            'total' => 0,
                        ];
                    }
                    $breakdown[$type]['total'] += $bd['total'] * $item->quantity;
                }
            }
        }

        foreach ($breakdown as &$bd) {
            $bd['total'] = round($bd['total'], 2);
        }

        return array_values($breakdown);
    }

    public function getFundFlowAttribute()
    {
        if (!$this->relationLoaded('items')) {
            return [
                'nodes' => [],
                'edges' => [],
                'description' => '',
            ];
        }

        $totalAmount = (float) $this->total_amount;
        $productCost = (float) $this->product_cost;
        $platformFee = (float) $this->platform_fee;
        $otherCost = (float) $this->other_cost;
        $totalCost = (float) $this->total_cost;
        $totalProfit = (float) $this->total_profit;
        $supplierShare = (float) $this->supplier_share;
        $distributorShare = (float) $this->distributor_share;
        $platformShare = (float) $this->platform_share;

        $nodes = [
            [
                'id' => 'customer',
                'name' => '客户',
                'amount' => $totalAmount,
                'type' => 'source',
                'description' => '销售收款',
            ],
            [
                'id' => 'platform',
                'name' => '平台账户',
                'amount' => $totalAmount,
                'type' => 'transfer',
                'description' => '资金归集',
            ],
            [
                'id' => 'product_cost',
                'name' => '商品成本',
                'amount' => $productCost,
                'type' => 'cost',
                'description' => '供应商货款',
            ],
            [
                'id' => 'platform_fee',
                'name' => '平台费用',
                'amount' => $platformFee,
                'type' => 'cost',
                'description' => '平台服务费预扣',
            ],
            [
                'id' => 'other_cost',
                'name' => '其他成本',
                'amount' => $otherCost,
                'type' => 'cost',
                'description' => '其他杂费',
            ],
            [
                'id' => 'profit',
                'name' => '可分配利润',
                'amount' => $totalProfit,
                'type' => 'profit',
                'description' => '利润总额',
            ],
            [
                'id' => 'supplier',
                'name' => '供应商',
                'amount' => $supplierShare,
                'type' => 'recipient',
                'description' => '利润分成',
            ],
            [
                'id' => 'distributor',
                'name' => '分销商',
                'amount' => $distributorShare,
                'type' => 'recipient',
                'description' => '利润分成',
            ],
            [
                'id' => 'platform_income',
                'name' => '平台收益',
                'amount' => $platformShare,
                'type' => 'recipient',
                'description' => '利润分成',
            ],
        ];

        $edges = [
            ['from' => 'customer', 'to' => 'platform', 'amount' => $totalAmount, 'label' => '销售总额'],
            ['from' => 'platform', 'to' => 'product_cost', 'amount' => $productCost, 'label' => '商品成本'],
            ['from' => 'platform', 'to' => 'platform_fee', 'amount' => $platformFee, 'label' => '平台费用'],
            ['from' => 'platform', 'to' => 'other_cost', 'amount' => $otherCost, 'label' => '其他成本'],
            ['from' => 'platform', 'to' => 'profit', 'amount' => $totalProfit, 'label' => '剩余利润'],
            ['from' => 'profit', 'to' => 'supplier', 'amount' => $supplierShare, 'label' => $this->supplier_ratio * 100 . '%'],
            ['from' => 'profit', 'to' => 'distributor', 'amount' => $distributorShare, 'label' => $this->distributor_ratio * 100 . '%'],
            ['from' => 'profit', 'to' => 'platform_income', 'amount' => $platformShare, 'label' => $this->platform_ratio * 100 . '%'],
        ];

        $description = sprintf(
            '资金流向：客户支付 ¥%s → 平台归集后，扣除商品成本 ¥%s、平台费用 ¥%s、其他成本 ¥%s，剩余利润 ¥%s 按比例分配：供应商 %s%% (¥%s)、分销商 %s%% (¥%s)、平台 %s%% (¥%s)。',
            format_money($totalAmount),
            format_money($productCost),
            format_money($platformFee),
            format_money($otherCost),
            format_money($totalProfit),
            $this->supplier_ratio * 100,
            format_money($supplierShare),
            $this->distributor_ratio * 100,
            format_money($distributorShare),
            $this->platform_ratio * 100,
            format_money($platformShare)
        );

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'description' => $description,
            'total_amount' => $totalAmount,
            'total_cost' => $totalCost,
            'total_profit' => $totalProfit,
        ];
    }

    public function getWithholdFormulaAttribute()
    {
        $totalAmount = (float) $this->total_amount;
        $productCost = (float) $this->product_cost;
        $platformFee = (float) $this->platform_fee;
        $otherCost = (float) $this->other_cost;
        $totalCost = (float) $this->total_cost;
        $totalProfit = (float) $this->total_profit;

        $formulas = [
            [
                'name' => '销售总额',
                'formula' => '销售总额 = Σ(商品单价 × 数量)',
                'value' => $totalAmount,
                'calculation' => $this->items->map(function($item) {
                    return "¥{$item->sale_price} × {$item->quantity}";
                })->implode(' + '),
            ],
            [
                'name' => '商品成本',
                'formula' => '商品成本 = Σ(单位成本 × 数量)',
                'value' => $productCost,
                'calculation' => $this->items->map(function($item) {
                    return "¥{$item->unit_cost} × {$item->quantity}";
                })->implode(' + '),
            ],
            [
                'name' => '预扣费用合计',
                'formula' => '预扣费用 = 平台费用 + 其他成本',
                'value' => round($platformFee + $otherCost, 2),
                'calculation' => "¥{$platformFee} + ¥{$otherCost}",
            ],
            [
                'name' => '总成本',
                'formula' => '总成本 = 商品成本 + 平台费用 + 其他成本',
                'value' => $totalCost,
                'calculation' => "¥{$productCost} + ¥{$platformFee} + ¥{$otherCost}",
            ],
            [
                'name' => '利润总额',
                'formula' => '利润总额 = 销售总额 - 总成本',
                'value' => $totalProfit,
                'calculation' => "¥{$totalAmount} - ¥{$totalCost}",
            ],
            [
                'name' => '利润率',
                'formula' => '利润率 = 利润总额 ÷ 销售总额 × 100%',
                'value' => (float) $this->profit_rate,
                'calculation' => $totalAmount > 0 ? "¥{$totalProfit} ÷ ¥{$totalAmount} × 100%" : 'N/A',
                'is_percent' => true,
            ],
            [
                'name' => '供应商分成',
                'formula' => '供应商分成 = 利润总额 × 供应商分成比例',
                'value' => (float) $this->supplier_share,
                'calculation' => "¥{$totalProfit} × " . ($this->supplier_ratio * 100) . "%",
            ],
            [
                'name' => '分销商分成',
                'formula' => '分销商分成 = 利润总额 × 分销商分成比例',
                'value' => (float) $this->distributor_share,
                'calculation' => "¥{$totalProfit} × " . ($this->distributor_ratio * 100) . "%",
            ],
            [
                'name' => '平台分成',
                'formula' => '平台分成 = 利润总额 × 平台分成比例',
                'value' => (float) $this->platform_share,
                'calculation' => "¥{$totalProfit} × " . ($this->platform_ratio * 100) . "%",
            ],
        ];

        return [
            'formulas' => $formulas,
            'summary' => sprintf(
                '本次结算共 %d 件商品，销售总额 ¥%s，扣除各项成本 ¥%s 后，实现利润 ¥%s，利润率 %s%%。',
                $this->items->count(),
                format_money($totalAmount),
                format_money($totalCost),
                format_money($totalProfit),
                $totalAmount > 0 ? round($this->profit_rate * 100, 2) : '0'
            ),
        ];
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeOfStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function scopeSettled($query)
    {
        return $query->where('status', self::STATUS_SETTLED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('settlement_date', [$startDate, $endDate]);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CANCELLED]);
    }

    public function canConfirm(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canSettle(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED]);
    }

    public function recalculateTotals(): self
    {
        $orderCount = 0;
        $totalAmount = 0;
        $productCost = 0;
        $totalProfit = 0;

        foreach ($this->items as $item) {
            $orderCount++;
            $totalAmount += (float) $item->total_sales;
            $productCost += (float) $item->total_cost;
            $totalProfit += (float) $item->profit;
        }

        $this->order_count = $orderCount;
        $this->total_amount = round($totalAmount, 2);
        $this->product_cost = round($productCost, 2);
        $this->total_cost = round($productCost + (float) $this->platform_fee + (float) $this->other_cost, 2);
        $this->total_profit = round($totalProfit - (float) $this->platform_fee - (float) $this->other_cost, 2);
        $this->profit_rate = $totalAmount > 0 ? round($this->total_profit / $totalAmount, 4) : 0;

        $supplierRatio = (float) ($this->supplier_ratio ?? config('shearerline.settlement.default_supplier_ratio', 0.5));
        $distributorRatio = (float) ($this->distributor_ratio ?? config('shearerline.settlement.default_distributor_ratio', 0.2));
        $platformRatio = (float) ($this->platform_ratio ?? config('shearerline.settlement.default_platform_ratio', 0.3));

        $this->supplier_share = round($this->total_profit * $supplierRatio, 2);
        $this->distributor_share = round($this->total_profit * $distributorRatio, 2);
        $this->platform_share = round($this->total_profit * $platformRatio, 2);

        return $this;
    }
}
