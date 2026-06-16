<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Models\ProductDailySale;
use Illuminate\Support\Facades\Redis;



class ProductController extends Controller
{
    public function index()
    {
        return response()->json(
            Product::query()
                ->select('id', 'name', 'price', 'cost_price', 'stock')
                ->paginate(25)
        );
    }

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
            ->select('id', 'name', 'price', 'cost_price', 'stock')
            ->get()
            ->filter(fn($product) => str_contains(strtolower($product->name), $q))
            ->values();

        return response()->json([
            'status' => 'success',
            'count' => $products->count(),
            'data' => $products,
        ]);
    }

###################بدون كاش
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
            ->select('id', 'name', 'price', 'cost_price', 'stock')
            ->when($q !== '', fn($query) => $query->where('name', 'like', $q . '%'))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => 'success',
            'count' => $products->count(),
            'data' => $products,
        ]);
    }

   #####مع كاش 
public function searchCache(Request $request)
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

    //  توليد مفتاح كاش فريد بناءً على نص البحث والـ limit
    $cacheKey = "search_products_" . md5($q . "_" . $limit);

    try {
        //  محاولة جلب نتائج البحث من الـ Redis
        $cachedResult = Cache::get($cacheKey);

        // التحقق من أن الكاش موجود وليس فارغاً لضمان عدم حدوث تداخل
        if ($cachedResult && is_array($cachedResult)) {
            try { Redis::incr('search_cache_hits'); } catch (\Throwable $e) {}
            
            //  تسجيل الـ Hit لـ JMeter
            Log::info("[Benchmark] جلب نتائج البحث من الـ Redis لكلمة: '{$q}'");

            return response()->json([
                'status' => 'success',
                'source' => 'redis',
                'count' => count($cachedResult),
                'data' => $cachedResult,
            ]);
        }
        try { Redis::incr('search_cache_misses'); } catch (\Throwable $e) {}
    } catch (\Throwable $e) {}

    // في حال الـ Cache Miss: الذهاب للاستعلام الثقيل في الـ DB
    Log::warning("[Benchmark] Cache Miss! استعلام ثقيل من الـ DB للبحث عن كلمة: '{$q}'");


    $products = Product::query()
        ->select('id', 'name', 'price', 'cost_price', 'stock')
        ->when($q !== '', fn($query) => $query->where('name', 'like', $q . '%'))
        ->orderBy('id')
        ->limit($limit)
        ->get();

    // تحويل الـ Eloquent Collection إلى مصفوفة صافية (Array) قبل تخزينها في الـ Redis
    $productsArray = $products->toArray();

    try {
        Cache::put($cacheKey, $productsArray, now()->addMinutes(10));
    } catch (\Throwable $e) {}

    return response()->json([
        'status' => 'success',
        'source' => 'database',
        'count' => count($productsArray),
        'data' => $productsArray,
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

        // add stock
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
            'cost_price' => 'required|numeric|min:1',
            'stock' => 'required|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $product = Product::create([
            'name' => $request->name,
            'price' => $request->price,
            'cost_price' => $request->cost_price,
            'stock' => $request->stock,
        ]);

        return response()->json([
            'message' => 'product created successfully',
            'product' => $product
        ], 201);
    }
    #--------------------------------------------------------------
   #Broken
public function topSellingProductsBroken()
{
    config(['session.driver' => 'array']);

    Log::info("[Benchmark] طلب مباشر من جلب DB بدون كاش");

    $products = ProductDailySale::query()
        ->selectRaw('product_id, SUM(quantity_sold) as total_sold')
        ->with('product:id,name')
        ->groupBy('product_id')
        ->orderByDesc('total_sold')
        ->take(10)
        ->get()
        ->map(function ($row) {
            return [
                'product_id' => $row->product_id,
                'name'       => $row->product?->name,
                'total_sold' => (int) $row->total_sold
            ];
        });

    return response()->json([
        'source' => 'database',
        'data'   => $products
    ]);
}

public function cachedProductBroken($product_id)
{
    //  تسجيل في اللوغ للمنتج 
    Log::info("[Benchmark] طلب مباشر من جلب DB بدون كاش للمنتج ID: {$product_id}");

    $product = Product::find($product_id);

    if (!$product) {
        return response()->json([
            'message' => 'product not found'
        ], 404);
    }

    return response()->json([
        'source' => 'database',
        'product' => $product
    ]);
}



#fixed
public function topSellingProducts()
{
    $cacheKey = 'top_selling_products';

    try {
        $cached = Cache::get($cacheKey);
        if ($cached) {
            try { Redis::incr('cache_hits'); } catch (\Throwable $e) {}
            
            //  تسجيل الـ Hit (تم الجلب من Redis بنجاح)
            Log::info("[Benchmark] جلب كاش من الـ Redis");

            return response()->json([
                'source' => 'redis',
                'data' => $cached
            ]);
        }
        try { Redis::incr('cache_misses'); } catch (\Throwable $e) {}
    } catch (\Throwable $e) {
        $sourceFallback = 'database_fallback';
    }

    // تسجيل الـ Miss (البيانات غير موجودة وضُربت الداتابيز)
    Log::warning("[Benchmark] Cache Miss! جلب البيانات من الـ DB وتخزينها في الكاش");

    $products = ProductDailySale::query()
        ->selectRaw('product_id, SUM(quantity_sold) as total_sold')
        ->with('product:id,name')
        ->groupBy('product_id')
        ->orderByDesc('total_sold')
        ->take(10)
        ->get()
        ->map(function ($row) {
            return [
                'product_id' => $row->product_id,
                'name'       => $row->product?->name,
                'total_sold' => (int) $row->total_sold
            ];
        });

    if (!isset($sourceFallback)) {
        try {
            Cache::put($cacheKey, $products->toArray(), now()->addMinutes(10));
        } catch (\Throwable $e) {}
    }

    return response()->json([
        'source' => $sourceFallback ?? 'database',
        'data'   => $products
    ]);
}

public function cachedProduct($product_id)
{
    $cacheKey = "product_details_{$product_id}";

    try {
        $cached = Cache::get($cacheKey);
        if ($cached) {
            try { Redis::incr('cache_hits'); } catch (\Throwable $e) {}
            
            // 📝 تسجيل الـ Hit للمنتج
            Log::info("[Benchmark] جلب كاش من الـ Redis للمنتج ID: {$product_id}");

            return response()->json([
                'source'  => 'redis',
                'product' => $cached
            ]);
        }
        try { Redis::incr('cache_misses'); } catch (\Throwable $e) {}
    } catch (\Throwable $e) {
        $sourceFallback = 'database_fallback';
    }

    Log::warning("[Benchmark] Cache Miss! جلب البيانات من الـ DB وتخزينها في الكاش للمنتج ID: {$product_id}");
    
    // جلب المنتج من الداتا بيز
    $product = Product::findOrFail($product_id);

    // تخزينه في الكاش لمدة ساعة (3600 ثانية) للطلبات القادمة
    try {
        Cache::put($cacheKey, $product, now()->addMinutes(60));
    } catch (\Throwable $e) {}

    return response()->json([
        'source'  => isset($sourceFallback) ? $sourceFallback : 'database',
        'product' => $product
    ]);
}
public function cacheStats()
{
    try {
        $hits   = (int) (Redis::get('cache_hits')   ?? 0);
        $misses = (int) (Redis::get('cache_misses') ?? 0);
    } catch (\Throwable $e) {
        return response()->json([
            'message' => 'Cache monitoring stats unavailable (Redis connection lost).'
        ], 503);
    }

    $total = $hits + $misses;
    $ratio = $total > 0 ? round(($hits / $total) * 100, 2) : 0;

    return response()->json([
        'hits'      => $hits,
        'misses'    => $misses,
        'hit_ratio' => $ratio . '%'
    ]);
}

public function clearCache()
{
    try {
        Cache::flush();
        Redis::set('cache_hits',   0);
        Redis::set('cache_misses', 0);
        return response()->json(['message' => 'cache cleared']);
    } catch (\Throwable $e) {
        return response()->json([
            'message' => 'Failed to clear cache: Redis is unreachable.'
        ], 500);
    }
}
}
