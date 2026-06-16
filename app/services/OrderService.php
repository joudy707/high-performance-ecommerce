<?php
namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\OrderItems;
use Illuminate\Support\Facades\Auth;

class OrderService
{
    protected $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    public function createOrder(array $items)
    {
        $user = Auth::user();

        $order = Order::create([
            'user_id' => $user->id,
            'status' => 'pending',
            'total_price' => 0
        ]);

        $total = 0;

        foreach ($items as $item) {
            $product = Product::findOrFail($item['product_id']);

            // خصم المخزون
            $this->stockService->decreaseStock($product, $item['quantity']);

            $price = $product->price * $item['quantity'];

            OrderItems::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $item['quantity'],
                'price' => $price
            ]);

            $total += $price;
        }

        $order->update(['total_price' => $total]);

        // baseline (sync)
        // app(\App\Actions\CreateInvoiceAction::class)->execute($order);
        // app(\App\Actions\SendNotificationAction::class)->execute($order);

        return $order->load('items');
    }
}