<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

class MetricsController extends Controller
{
    private array $modules = ['auth', 'products', 'orders', 'inventory', 'payments'];

    public function prometheus(): Response
    {
        $entries = $this->loadAllLogEntries();
        $stats = $this->calculateStats($entries);
        $lines = [];

        $lines[] = '# HELP laravel_response_time_avg_ms Average response time per endpoint in ms';
        $lines[] = '# TYPE laravel_response_time_avg_ms gauge';
        foreach ($stats as $endpoint => $s) {
            $label = $this->sanitizeLabel($endpoint);
            $lines[] = "laravel_response_time_avg_ms{endpoint=\"{$label}\",module=\"{$s['module']}\"} {$s['avg_ms']}";
        }

        $lines[] = '';
        $lines[] = '# HELP laravel_response_time_p95_ms P95 response time per endpoint in ms';
        $lines[] = '# TYPE laravel_response_time_p95_ms gauge';
        foreach ($stats as $endpoint => $s) {
            $label = $this->sanitizeLabel($endpoint);
            $lines[] = "laravel_response_time_p95_ms{endpoint=\"{$label}\",module=\"{$s['module']}\"} {$s['p95_ms']}";
        }

        $lines[] = '';
        $lines[] = '# HELP laravel_response_time_max_ms Max response time per endpoint in ms';
        $lines[] = '# TYPE laravel_response_time_max_ms gauge';
        foreach ($stats as $endpoint => $s) {
            $label = $this->sanitizeLabel($endpoint);
            $lines[] = "laravel_response_time_max_ms{endpoint=\"{$label}\",module=\"{$s['module']}\"} {$s['max_ms']}";
        }

        $lines[] = '';
        $lines[] = '# HELP laravel_request_count Total request count per endpoint';
        $lines[] = '# TYPE laravel_request_count counter';
        foreach ($stats as $endpoint => $s) {
            $label = $this->sanitizeLabel($endpoint);
            $lines[] = "laravel_request_count{endpoint=\"{$label}\",module=\"{$s['module']}\"} {$s['count']}";
        }

        $lines[] = '';
        $lines[] = '# HELP laravel_error_rate_pct Error rate percentage per endpoint';
        $lines[] = '# TYPE laravel_error_rate_pct gauge';
        foreach ($stats as $endpoint => $s) {
            $label = $this->sanitizeLabel($endpoint);
            $lines[] = "laravel_error_rate_pct{endpoint=\"{$label}\",module=\"{$s['module']}\"} {$s['error_rate']}";
        }

        $lines[] = '';
        $lines[] = '# HELP laravel_up Is the metrics endpoint healthy';
        $lines[] = '# TYPE laravel_up gauge';
        $lines[] = 'laravel_up 1';

        return new Response(implode("\n", $lines) . "\n", 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }

    public function json(): JsonResponse
    {
        $entries = $this->loadAllLogEntries();
        $stats = $this->calculateStats($entries);

        return response()->json([
            'timestamp'  => now()->toIso8601String(),
            'server'     => gethostname(),
            'endpoints'  => $stats,
            'bottleneck' => $this->findBottleneck($stats),
        ]);
    }

    private function loadAllLogEntries(): array
    {
        $entries = [];

        foreach ($this->modules as $module) {
            $path = storage_path("logs/{$module}.log");
            if (!file_exists($path)) {
                continue;
            }

            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $entry = $this->parseLine($line);
                if ($entry) {
                    $entries[] = $entry;
                }
            }
        }

        return $entries;
    }

    private function parseLine(string $line): ?array
    {
        $pattern = '/^\[([^\]]+)\] \[([^\]]+)\] \[([^\]]+)\] \[([^\]]+)\] \[([0-9.]+)\] \[(\d+)\] \[([^\]]+)\]$/';
        if (!preg_match($pattern, $line, $m)) {
            return null;
        }

        return [
            'timestamp' => $m[1],
            'module'    => strtolower($m[2]),
            'endpoint'  => $m[3],
            'method'    => $m[4],
            'duration'  => (float) $m[5],
            'status'    => (int) $m[6],
            'request_id' => $m[7],
        ];
    }

    private function calculateStats(array $entries): array
    {
        $grouped = [];

        foreach ($entries as $e) {
            $endpointPath = preg_replace('#^api/|^/#', '', $e['endpoint']);
            $key = $e['method'] . ' /' . $endpointPath;
            $grouped[$key][] = $e;
        }

        $stats = [];

        foreach ($grouped as $endpoint => $points) {
            $durations = array_column($points, 'duration');
            $statuses = array_column($points, 'status');
            $count = count($durations);
            $totalErrors = count(array_filter($statuses, fn($s) => $s >= 400));

            sort($durations);

            $avg = array_sum($durations) / $count;
            $max = max($durations);
            $p95Index = (int) ceil(0.95 * $count) - 1;
            $p95 = $durations[max(0, $p95Index)];

            $stats[$endpoint] = [
                'count'      => $count,
                'avg_ms'     => round($avg, 2),
                'p95_ms'     => round($p95, 2),
                'max_ms'     => round($max, 2),
                'error_rate' => $count > 0 ? round(($totalErrors / $count) * 100, 2) : 0,
                'module'     => $points[0]['module'],
            ];
        }

        return $stats;
    }

    private function findBottleneck(array $stats): ?array
    {
        if (empty($stats)) {
            return null;
        }

        $sorted = $stats;
        usort($sorted, fn($a, $b) => $b['avg_ms'] <=> $a['avg_ms']);

        return $sorted[0];
    }

    private function sanitizeLabel(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_\/\-:.]/', '_', $name);
    }
}