<?php

namespace Shearerline\Http\Controllers\Api;

use Shearerline\Http\Controllers\Controller;
use Shearerline\Http\Requests\StoreSettlementRequest;
use Shearerline\Http\Requests\UpdateSettlementRequest;
use Shearerline\Http\Requests\CalculateSettlementRequest;
use Shearerline\Models\Settlement;
use Shearerline\Shearerline;
use Illuminate\Http\Request;
use Shearerline\Exceptions\SettlementStateException;

class SettlementController extends Controller
{
    protected $shearerline;

    public function __construct(Shearerline $shearerline)
    {
        $this->shearerline = $shearerline;
    }

    public function index(Request $request)
    {
        $filters = $request->only([
            'keyword', 'type', 'status', 'start_date', 'end_date', 'per_page'
        ]);

        $settlements = $this->shearerline->getSettlements($filters);

        return $this->paginated($settlements);
    }

    public function calculate(CalculateSettlementRequest $request)
    {
        $items = $request->input('items', []);
        $ratios = $request->only([
            'settlement_date', 'supplier_ratio', 'distributor_ratio',
            'platform_ratio', 'platform_fee', 'other_cost'
        ]);

        $result = $this->shearerline->calculateSettlement($items, $ratios);

        return $this->success($result, '结算预览计算成功');
    }

    public function store(StoreSettlementRequest $request)
    {
        $settlement = $this->shearerline->createSettlement($request->validated());

        return $this->success($settlement, '结算单创建成功', 201);
    }

    public function show(Settlement $settlement)
    {
        $settlement = $this->shearerline->getSettlement($settlement->id);

        return $this->success($settlement);
    }

    public function update(UpdateSettlementRequest $request, Settlement $settlement)
    {
        try {
            $settlement = $this->shearerline->updateSettlement($settlement->id, $request->validated());
            return $this->success($settlement, '结算单更新成功');
        } catch (SettlementStateException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function confirm(Settlement $settlement)
    {
        try {
            $settlement = $this->shearerline->confirmSettlement($settlement->id);
            return $this->success($settlement, '结算单确认成功');
        } catch (SettlementStateException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function settle(Settlement $settlement)
    {
        try {
            $settlement = $this->shearerline->settleSettlement($settlement->id);
            return $this->success($settlement, '结算单结算成功');
        } catch (SettlementStateException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function cancel(Settlement $settlement)
    {
        try {
            $settlement = $this->shearerline->cancelSettlement($settlement->id);
            return $this->success($settlement, '结算单已取消');
        } catch (SettlementStateException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function types()
    {
        return $this->success([
            'types' => $this->shearerline->getSettlementTypes(),
            'statuses' => $this->shearerline->getSettlementStatuses(),
        ]);
    }

    public function destroy(Settlement $settlement)
    {
        if (!$settlement->isEditable()) {
            return $this->error('当前状态不可删除', 422);
        }

        $settlement->items()->delete();
        $settlement->delete();

        return $this->success(null, '结算单删除成功');
    }
}
