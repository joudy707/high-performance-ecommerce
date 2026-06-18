<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PerformanceMonitor
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);
        
        $duration = round((microtime(true) - $start) * 1000, 2);
        
        if ($response->getStatusCode() !== 404) {
            Log::channel('single')->info('[AOP - Performance Monitor]', [
                'method'   => $request->method(),
                'url'      => $request->url(),
                'duration' => $duration . 'ms',
                'status'   => $response->getStatusCode(),
            ]);
        }

        return $response;
    }
}