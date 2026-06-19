<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PerformanceMonitor
{
    protected array $modulePatterns = [
        'auth'      => ['/register', '/login'],
        'products'  => ['/products', '/cache', '/products-search'],
        'orders'    => ['/order', '/orders'],
        'inventory' => ['/cart', '/stock'],
        'payments'  => ['distributed', 'acid'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        $requestId = (string) Str::uuid();

        $response = $next($request);

        $duration = round((microtime(true) - $start) * 1000, 2);

        if ($response->getStatusCode() === 404) {
            return $response;
        }

        $url = $request->url();
        $method = $request->method();
        $status = $response->getStatusCode();
        $module = $this->detectModule($url);
        $endpoint = $request->path();

        $logLine = sprintf(
            "[%s] [%s] [%s] [%s] [%.2f] [%d] [%s]",
            now()->toIso8601String(),
            strtoupper($module),
            $endpoint,
            $method,
            $duration,
            $status,
            $requestId
        );

        Log::channel($module)->info($logLine);
        Log::channel('benchmark')->info($logLine);

        return $response;
    }

    protected function detectModule(string $url): string
    {
        foreach ($this->modulePatterns as $module => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($url, $pattern)) {
                    return $module;
                }
            }
        }

        return 'benchmark';
    }
}