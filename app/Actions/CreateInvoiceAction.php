<?php 
namespace App\Actions;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

class CreateInvoiceAction
{
    public function execute(Order $order)
    {
        // حاليا فقط log
        Log::info("Invoice created for order {$order->id}");
    }
}