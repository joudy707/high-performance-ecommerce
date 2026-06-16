<?php

namespace App\Jobs;

use App\Actions\SendNotificationAction;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

   
    public int $tries = 3;

    public array $backoff = [5, 10, 15];

    public function __construct(public Order $order)
    {
        $this->queue = 'notifications';
    }

    
    public function handle(): void
    {
        Log::info("[SendNotificationJob] Sending notification for Order #{$this->order->id}");

      
        sleep(2);

        app(SendNotificationAction::class)->execute($this->order);

        Log::info("[SendNotificationJob] Notification successfully sent for Order #{$this->order->id}");
    }

   
    public function failed(Throwable $exception): void
    {
        Log::error(
            "[SendNotificationJob] FAILED permanently for Order #{$this->order->id}. " .
            "Moved to Dead Letter Queue. Error: {$exception->getMessage()}"
        );

    }
}
