<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;


class GenerateReportFixedLock implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Unique key per day — prevents running the same day's report twice
        $lockKey = 'lock:report-job:' . today()->toDateString();

        // TTL = 10 minutes — auto-releases if the server crashes mid-job
        $lock = Cache::lock($lockKey, 600);

        // Atomic lock acquisition on Redis
        // If another server holds this key → get() returns false immediately
        if (!$lock->get()) {
            Log::warning(
                "[REPORT-FIXED-LOCK] Server [" . gethostname() . "] tried to start report job"
                . " for " . today()->toDateString()
                . " — REJECTED. Lock already held by another server. Skipping."
            );
            return; 
        }

        try {
            Log::info(
                "[REPORT-FIXED-LOCK] Server [" . gethostname() . "] acquired Redis lock"
                . " and started report job for " . today()->toDateString()
            );

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
                ->name('report-fixed-lock-' . today()->toDateString())
                // Release lock only AFTER all chunks finish (not after dispatch)
                // dispatch() returns immediately — chunks run async in background
                ->finally(function () use ($lock) {
                    $lock->release();
                    Log::info("[REPORT-FIXED-LOCK] All chunks finished. Redis lock released.");
                })
                ->dispatch();

            Log::info(
                "[REPORT-FIXED-LOCK] Server [" . gethostname() . "] dispatched "
                . count($chunks) . " chunk(s). Lock will be released after batch completes."
            );

        } catch (\Exception $e) {
            // Always release if dispatch itself fails (before batch starts)
            $lock->release();
            Log::error("[REPORT-FIXED-LOCK] Dispatch failed: " . $e->getMessage() . " — lock released.");
            throw $e;
        }
    }
}
