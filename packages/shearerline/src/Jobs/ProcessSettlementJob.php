<?php

namespace Shearerline\Jobs;

use Shearerline\Models\Settlement;
use Shearerline\Services\SettlementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSettlementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $settlementId;
    public $timeout = 120;
    public $tries = 3;

    public function __construct(int $settlementId)
    {
        $this->settlementId = $settlementId;
        $this->onQueue(config('shearerline.queue.settlement_queue', 'shearerline-settlements'));
        $this->onConnection(config('shearerline.queue.connection', 'database'));
    }

    public function handle(SettlementService $settlementService): void
    {
        $settlement = Settlement::with('items')->find($this->settlementId);

        if (!$settlement) {
            Log::warning('[Shearerline] Settlement not found for processing', ['id' => $this->settlementId]);
            return;
        }

        try {
            $settlement->recalculateTotals()->save();

            Log::info('[Shearerline] Settlement processed successfully', [
                'settlement_id' => $settlement->id,
                'settlement_no' => $settlement->settlement_no,
                'total_amount' => $settlement->total_amount,
                'total_profit' => $settlement->total_profit,
            ]);
        } catch (\Exception $e) {
            Log::error('[Shearerline] Settlement processing failed', [
                'settlement_id' => $this->settlementId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }
}
