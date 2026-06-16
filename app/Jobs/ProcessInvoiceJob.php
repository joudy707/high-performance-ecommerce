<?php

namespace App\Jobs;

use App\Actions\CreateInvoiceAction;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

   
    public int $tries = 3;

    public array $backoff = [5, 10, 15];

  
    public function __construct(public Order $order)
    {
        $this->queue = 'invoices';
    }

    
    public function handle(): void
    {
        Log::info("[ProcessInvoiceJob] Starting invoice generation for Order #{$this->order->id}");

     
        sleep(3);

        app(CreateInvoiceAction::class)->execute($this->order);

        Log::info("[ProcessInvoiceJob] Invoice successfully generated for Order #{$this->order->id}");
    }

  
    public function failed(Throwable $exception): void
    {
        Log::error(
            "[ProcessInvoiceJob] FAILED permanently for Order #{$this->order->id}. " .
            "Moved to Dead Letter Queue. Error: {$exception->getMessage()}"
        );

    }
}
