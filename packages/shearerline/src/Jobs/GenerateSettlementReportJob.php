<?php

namespace Shearerline\Jobs;

use Shearerline\Models\Settlement;
use Shearerline\Services\FundFlowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateSettlementReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $settlementId;
    public $reportFormat;
    public $timeout = 300;
    public $tries = 2;

    public function __construct(int $settlementId, string $format = 'json')
    {
        $this->settlementId = $settlementId;
        $this->reportFormat = $format;
        $this->onQueue(config('shearerline.queue.report_queue', 'shearerline-reports'));
        $this->onConnection(config('shearerline.queue.connection', 'database'));
    }

    public function handle(FundFlowService $fundFlowService): void
    {
        $settlement = Settlement::with('items')->find($this->settlementId);

        if (!$settlement) {
            Log::warning('[Shearerline] Settlement not found for report generation', ['id' => $this->settlementId]);
            return;
        }

        try {
            $fundFlow = $fundFlowService->buildFundFlowFromSettlement($settlement);
            $withholdFormula = $fundFlowService->buildWithholdFormulaFromSettlement($settlement);
            $costBreakdown = $fundFlowService->buildProductCostBreakdown($settlement->items);

            $reportData = [
                'settlement' => [
                    'id' => $settlement->id,
                    'settlement_no' => $settlement->settlement_no,
                    'type' => $settlement->type,
                    'type_name' => $settlement->type_name,
                    'settlement_date' => $settlement->settlement_date->toDateString(),
                    'status' => $settlement->status,
                    'status_name' => $settlement->status_name,
                ],
                'summary' => [
                    'order_count' => $settlement->order_count,
                    'total_amount' => $settlement->total_amount,
                    'product_cost' => $settlement->product_cost,
                    'platform_fee' => $settlement->platform_fee,
                    'other_cost' => $settlement->other_cost,
                    'total_cost' => $settlement->total_cost,
                    'total_profit' => $settlement->total_profit,
                    'profit_rate' => $settlement->profit_rate,
                ],
                'shares' => [
                    'supplier_ratio' => $settlement->supplier_ratio,
                    'distributor_ratio' => $settlement->distributor_ratio,
                    'platform_ratio' => $settlement->platform_ratio,
                    'supplier_share' => $settlement->supplier_share,
                    'distributor_share' => $settlement->distributor_share,
                    'platform_share' => $settlement->platform_share,
                ],
                'fund_flow' => $fundFlow,
                'withhold_formula' => $withholdFormula,
                'product_cost_breakdown' => $costBreakdown,
                'items' => $settlement->items->map(function ($item) {
                    return [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'product_sku' => $item->product_sku,
                        'quantity' => $item->quantity,
                        'sale_price' => $item->sale_price,
                        'total_sales' => $item->total_sales,
                        'unit_cost' => $item->unit_cost,
                        'total_cost' => $item->total_cost,
                        'profit' => $item->profit,
                    ];
                }),
                'generated_at' => now()->toDateTimeString(),
            ];

            $fileName = "settlement-reports/{$settlement->settlement_no}.json";
            Storage::disk('local')->put($fileName, json_encode($reportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            Log::info('[Shearerline] Settlement report generated', [
                'settlement_id' => $settlement->id,
                'settlement_no' => $settlement->settlement_no,
                'file' => $fileName,
            ]);
        } catch (\Exception $e) {
            Log::error('[Shearerline] Settlement report generation failed', [
                'settlement_id' => $this->settlementId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function backoff(): array
    {
        return [30, 120];
    }
}
