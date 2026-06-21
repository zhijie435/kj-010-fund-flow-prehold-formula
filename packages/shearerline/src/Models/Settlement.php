<?php

namespace Shearerline\Models;

use Shearerline\Services\FundFlowService;
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
    ];

    protected static function booted(): void
    {
        static::retrieved(function ($model) {
            if (config('shearerline.append.fund_flow', true)) {
                $model->append('fund_flow');
            }
            if (config('shearerline.append.withhold_formula', true)) {
                $model->append('withhold_formula');
            }
        });
    }

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

        return app(FundFlowService::class)->buildProductCostBreakdown($this->items);
    }

    public function getFundFlowAttribute()
    {
        return app(FundFlowService::class)->buildFundFlowFromSettlement($this);
    }

    public function getWithholdFormulaAttribute()
    {
        return app(FundFlowService::class)->buildWithholdFormulaFromSettlement($this);
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
        return (new \Shearerline\StateMachines\SettlementStateMachine($this))->isEditable();
    }

    public function canConfirm(): bool
    {
        return (new \Shearerline\StateMachines\SettlementStateMachine($this))->canConfirm();
    }

    public function canSettle(): bool
    {
        return (new \Shearerline\StateMachines\SettlementStateMachine($this))->canSettle();
    }

    public function canCancel(): bool
    {
        return (new \Shearerline\StateMachines\SettlementStateMachine($this))->canCancel();
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
