<?php

namespace App\Http\Controllers;

use App\Actions\CreateInvoiceAction;
use App\Actions\SendNotificationAction;
use App\Jobs\ProcessInvoiceJob;
use App\Jobs\SendNotificationJob;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    protected $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function store(Request $request)
    {
        $order = $this->orderService->createOrder($request->items);

        return response()->json($order);
    }

    public function show($id)
    {
        return response()->json(
            Order::with('items')->findOrFail($id)
        );
    }

    public function showOrder($order_id)
    {
        $user = Auth::guard('api')->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        $order = Order::with('items.product')
            ->where('id', $order_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'order not found'
            ], 404);
        }

        return response()->json([
            'order_id' => $order->id,
            'status' => $order->status,
            'total_price' => $order->total_price,
            'items' => $order->items
        ]);
    }

    public function confirmOrderBrokenAsync($order_id)
    {
        $user = Auth::guard('api')->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $order = Order::where('id', $order_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'order not found'], 404);
        }

        $order->status = 'paid';
        $order->save();

        $startTime = microtime(true);

        Log::info("[BROKEN-ASYNC] Starting SYNCHRONOUS invoice for Order #{$order->id}");
        sleep(3); 
        app(CreateInvoiceAction::class)->execute($order);

        Log::info("[BROKEN-ASYNC] Starting SYNCHRONOUS notification for Order #{$order->id}");
        sleep(2); 
        app(SendNotificationAction::class)->execute($order);

        $elapsed = round((microtime(true) - $startTime) * 1000); // ms

        return response()->json([
            'message'         => 'order confirmed (BROKEN - sync blocking)',
            'order_id'        => $order->id,
            'status'          => $order->status,
            'processing_mode' => 'synchronous',
            'response_time_ms'=> $elapsed,
            'note'            => 'User waited for invoice + notification inside the request. Total ~5s.'
        ], 200);
    }

    public function confirmOrderFixedAsync($order_id)
    {
        $user = Auth::guard('api')->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $order = Order::where('id', $order_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'order not found'], 404);
        }

        $order->status = 'paid';
        $order->save();

        ProcessInvoiceJob::dispatch($order);
        SendNotificationJob::dispatch($order);

        Log::info("[FIXED-ASYNC] Jobs dispatched for Order #{$order->id}. Response sent immediately.");

        return response()->json([
            'message'         => 'order confirmed (FIXED - async)',
            'order_id'        => $order->id,
            'status'          => $order->status,           
        ], 200);
    }
}