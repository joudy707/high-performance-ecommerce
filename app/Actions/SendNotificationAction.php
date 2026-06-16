<?php
namespace App\Actions;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

class SendNotificationAction
{
    public function execute(Order $order)
    {
        Log::info("Notification sent for order {$order->id}");
    }
}