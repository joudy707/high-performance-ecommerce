<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;

class GenerateDailySalesReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
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
            ->name('daily-sales-report-' . today()->toDateString())
            ->dispatch();
    }
}