<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;


class GenerateReportBrokenLock implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Log::warning(
            "[REPORT-BROKEN-LOCK] Server [" . gethostname() . "] started report job"
            . " for " . today()->toDateString() . " — NO distributed lock used."
            . " If another server runs this concurrently, the report will be generated TWICE."
        );

        // Simulate the time it takes to query and prepare data (opens the race window)
        // Both servers reach here before either commits — the race condition is active
        sleep(3);

        $orderIds = Order::whereDate('created_at', today())
            ->where('status', 'paid')
            ->pluck('id')
            ->toArray();

        $chunks = array_chunk($orderIds, 500);

        $jobs = array_map(
            fn($chunk) => new ProcessSalesReportChunk($chunk),
            $chunks
        );

        Bus::batch($jobs)
            ->name('report-broken-lock-' . today()->toDateString() . '-' . gethostname())
            ->dispatch();

        Log::warning(
            "[REPORT-BROKEN-LOCK] Server [" . gethostname() . "] dispatched "
            . count($chunks) . " chunk(s). "
            . "Check job_batches table — if two rows exist for today, the race condition occurred!"
        );
    }
}
