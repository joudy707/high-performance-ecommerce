<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
      public function index()
    {
        return response()->json(
            Product::query()
                ->select('id', 'name', 'price', 'stock')
                ->paginate(25)
        );
    }
<<<<<<< Updated upstream
=======

    public function show($product_id)
    {
        $product = Product::find($product_id);

        if (!$product) {
            return response()->json([
                'message' => 'product is not found'
            ], 404);
        }

        return response()->json([
            'product' => $product
        ], 200);
    }

    public function searchBroken(Request $request)
    {
        $q = strtolower((string) $request->query('q', ''));

        $products = Product::query()
            ->select('id', 'name', 'price', 'stock')
            ->get()
            ->filter(fn ($product) => str_contains(strtolower($product->name), $q))
            ->values();

        return response()->json([
            'status' => 'success',
            'count' => $products->count(),
            'data' => $products,
        ]);
    }


    public function searchFixed(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'q' => 'nullable|string|max:80',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $q = trim((string) $request->query('q', ''));
        $limit = (int) $request->query('limit', 20);

        $products = Product::query()
            ->select('id', 'name', 'price', 'stock')
            ->when($q !== '', fn ($query) => $query->where('name', 'like', $q . '%'))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => 'success',
            'count' => $products->count(),
            'data' => $products,
        ]);
    }

    public function addToStock(Request $request, $product_id)
    {
        $validator = Validator::make($request->all(), [
            'stock' => 'required|integer|min:1|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $product = Product::find($product_id);

        if (!$product) {
            return response()->json([
                'message' => 'product not found'
            ], 404);
        }

        $product->stock += $request->stock;
        $product->save();

        return response()->json([
            'message' => 'stock updated successfully',
            'new_stock' => $product->stock
        ]);
    }

    public function addProduct(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:1',
            'stock' => 'required|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $product = Product::create([
            'name' => $request->name,
            'price' => $request->price,
            'stock' => $request->stock,
        ]);

        return response()->json([
            'message' => 'product created successfully',
            'product' => $product
        ], 201);
    }
>>>>>>> Stashed changes
}
