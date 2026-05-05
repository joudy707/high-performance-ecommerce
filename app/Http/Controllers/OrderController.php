<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use Illuminate\Http\Request;

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
            \App\Models\Order::with('items')->findOrFail($id)
        );
    }
}