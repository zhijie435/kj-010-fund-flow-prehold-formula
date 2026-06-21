<?php

namespace Shearerline\Jobs;

use Shearerline\Models\Settlement;
use Shearerline\Services\FundFlowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RecalculateSettlementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $settlementId;
    public $timeout = 60;
    public $tries = 2;

    public function __construct(int $settlementId)
    {
        $this->settlementId = $settlementId;
        $this->onQueue(config('shearerline.queue.settlement_queue', 'shearerline-settlements'));
        $this->onConnection(config('shearerline.queue.connection', 'database'));
    }

    public function handle(FundFlowService $fundFlowService): void
    {
        $settlement = Settlement::with('items')->find($this->settlementId);

        if (!$settlement) {
            Log::warning('[Shearerline] Settlement not found for recalculation', ['id' => $this->settlementId]);
            return;
        }

        try {
            $settlement->recalculateTotals()->save();

            $fundFlow = $fundFlowService->buildFundFlowFromSettlement($settlement);
            $withholdFormula = $fundFlowService->buildWithholdFormulaFromSettlement($settlement);

            $cacheKey = "shearerline:settlement:{$settlement->id}:fund_flow";
            Cache::put($cacheKey, [
                'fund_flow' => $fundFlow,
                'withhold_formula' => $withholdFormula,
            ], now()->addHours(24));

            Log::info('[Shearerline] Settlement recalculated and cached', [
                'settlement_id' => $settlement->id,
                'settlement_no' => $settlement->settlement_no,
            ]);
        } catch (\Exception $e) {
            Log::error('[Shearerline] Settlement recalculation failed', [
                'settlement_id' => $this->settlementId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function backoff(): array
    {
        return [5, 15];
    }
}
