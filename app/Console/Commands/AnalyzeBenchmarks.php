<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AnalyzeBenchmarks extends Command
{
    protected $signature = 'benchmark:analyze
        {--before=logs/before : Directory with "before optimization" logs}
        {--after=logs/after : Directory with "after optimization" logs}
        {--export=metrics.json : File to export metrics JSON}';

    protected $description = 'Parse benchmark logs, calculate per-endpoint statistics, identify bottlenecks, and compare before/after';

    private array $modules = ['auth', 'products', 'orders', 'inventory', 'payments'];

    public function handle(): int
    {
        $this->info('=== Benchmark & Bottleneck Analysis ===');
        $this->newLine();

        $beforeDir = storage_path($this->option('before'));
        $afterDir = storage_path($this->option('after'));

        $hasBefore = is_dir($beforeDir) && count(File::files($beforeDir)) > 0;
        $hasAfter = is_dir($afterDir) && count(File::files($afterDir)) > 0;

        if (!$hasBefore || !$hasAfter) {
            $this->error('Both logs/before/ and logs/after/ directories must exist with log files.');
            $this->line('Run: php artisan benchmark:snapshot before  (after first stress test)');
            $this->line('Run: php artisan benchmark:snapshot after   (after second stress test)');
            return Command::FAILURE;
        }

        $beforeStats = $this->analyzeDirectory($beforeDir, 'Before', quiet: true);
        $afterStats = $this->analyzeDirectory($afterDir, 'After', quiet: true);

        $allEndpoints = array_unique(array_merge(array_keys($beforeStats), array_keys($afterStats)));
        sort($allEndpoints);

        $this->info('Before vs After Comparison');
        $this->newLine();

        $headers = ['Endpoint', 'Before (avg)', 'After (avg)', 'Improvement', 'Before (P95)', 'After (P95)'];
        $rows = [];

        foreach ($allEndpoints as $endpoint) {
            $b = $beforeStats[$endpoint] ?? null;
            $a = $afterStats[$endpoint] ?? null;

            $beforeAvg = $b ? round($b['avg_ms'], 2) . 'ms' : 'N/A';
            $afterAvg = $a ? round($a['avg_ms'], 2) . 'ms' : 'N/A';
            $beforeP95 = $b ? round($b['p95_ms'], 2) . 'ms' : 'N/A';
            $afterP95 = $a ? round($a['p95_ms'], 2) . 'ms' : 'N/A';

            if ($b && $a && $b['avg_ms'] > 0) {
                $pct = round((($b['avg_ms'] - $a['avg_ms']) / $b['avg_ms']) * 100, 1);
                $improvement = ($pct >= 0 ? $pct . '% faster' : abs($pct) . '% slower');
            } else {
                $improvement = 'N/A';
            }

            $rows[] = [$endpoint, $beforeAvg, $afterAvg, $improvement, $beforeP95, $afterP95];
        }

        $this->table($headers, $rows);
        $this->newLine();

        $this->identifyBottleneck($afterStats, 'After Optimization');

        $this->exportMetrics($afterStats, storage_path($this->option('export')));

        return Command::SUCCESS;
    }

    private function analyzeDirectory(string $dir, string $label, bool $quiet = false): array
    {
        $files = File::files($dir);
        $allEntries = [];

        foreach ($files as $file) {
            $module = $file->getFilenameWithoutExtension();
            if (!in_array($module, $this->modules, true)) {
                continue;
            }

            $lines = file($file->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $entry = $this->parseLogLine($line);
                if ($entry) {
                    $allEntries[] = $entry;
                }
            }
        }

        $stats = $this->calculateStatistics($allEntries);

        if (!$quiet) {
            $this->info("=== $label Analysis ===");
            $this->newLine();
            $this->displayStatistics($stats);
            $this->identifyBottleneck($stats, $label);
        }

        return $stats;
    }

    private function parseLogLine(string $line): ?array
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

    private function calculateStatistics(array $entries): array
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
                'count'       => $count,
                'avg_ms'      => $avg,
                'p95_ms'      => $p95,
                'max_ms'      => $max,
                'error_rate'  => $count > 0 ? round(($totalErrors / $count) * 100, 2) : 0,
                'errors'      => $totalErrors,
                'module'      => $points[0]['module'],
            ];
        }

        return $stats;
    }

    private function displayStatistics(array $stats): void
    {
        $headers = ['Endpoint', 'Module', 'Count', 'Avg (ms)', 'P95 (ms)', 'Max (ms)', 'Error Rate'];
        $rows = [];

        foreach ($stats as $endpoint => $s) {
            $rows[] = [
                $endpoint,
                $s['module'],
                $s['count'],
                round($s['avg_ms'], 2),
                round($s['p95_ms'], 2),
                round($s['max_ms'], 2),
                $s['error_rate'] . '%',
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();
    }

    private function identifyBottleneck(array $stats, string $label): void
    {
        if (empty($stats)) {
            $this->warn('No data available to identify bottleneck.');
            return;
        }

        $byAvg = [];
        $byP95 = [];

        foreach ($stats as $endpoint => $s) {
            $byAvg[] = ['endpoint' => $endpoint] + $s;
            $byP95[] = ['endpoint' => $endpoint] + $s;
        }

        usort($byAvg, fn($a, $b) => $b['avg_ms'] <=> $a['avg_ms']);
        usort($byP95, fn($a, $b) => $b['p95_ms'] <=> $a['p95_ms']);

        $worstAvg = $byAvg[0];
        $worstP95 = $byP95[0];

        $this->info("Bottleneck Identification ($label):");
        $this->newLine();

        $this->line("  By Average:  <fg=red>{$worstAvg['avg_ms']}ms</> on <options=bold>{$worstAvg['endpoint']}</>");
        $this->line("  By P95:      <fg=red>{$worstP95['p95_ms']}ms</> on <options=bold>{$worstP95['endpoint']}</>");
        $this->newLine();

        $bottleneck = $worstAvg['avg_ms'] >= $worstP95['p95_ms'] ? $worstAvg : $worstP95;

        $this->line("  <options=bold>Bottleneck identified:</> <fg=red>{$bottleneck['endpoint']}</>");
        $this->line("    Module:   {$bottleneck['module']}");
        $this->line("    Avg:      " . round($bottleneck['avg_ms'], 2) . "ms");
        $this->line("    P95:      " . round($bottleneck['p95_ms'], 2) . "ms");
        $this->line("    Max:      " . round($bottleneck['max_ms'], 2) . "ms");
        $this->line("    Requests: {$bottleneck['count']}");
        $this->line("    Errors:   {$bottleneck['error_rate']}%");
        $this->newLine();

        $avgRounded = round($bottleneck['avg_ms'], 2);
        $this->line("  > \"Bottleneck identified: {$bottleneck['endpoint']} — avg {$avgRounded}ms, P95 {$bottleneck['p95_ms']}ms under {$bottleneck['count']} concurrent users\"");
        $this->newLine();
    }

    private function exportMetrics(array $stats, string $path): void
    {
        $metrics = [];

        foreach ($stats as $endpoint => $s) {
            $safeName = preg_replace('/[^a-zA-Z0-9_\/]/', '_', $endpoint);
            $metrics["response_time_avg_ms{$safeName}"] = round($s['avg_ms'], 2);
            $metrics["response_time_p95_ms{$safeName}"] = round($s['p95_ms'], 2);
            $metrics["response_time_max_ms{$safeName}"] = round($s['max_ms'], 2);
            $metrics["request_count{$safeName}"] = $s['count'];
            $metrics["error_rate_pct{$safeName}"] = $s['error_rate'];
        }

        $payload = json_encode([
            'timestamp'  => now()->toIso8601String(),
            'server'     => gethostname(),
            'metrics'    => $metrics,
            'bottleneck' => $this->findBottleneckEntry($stats),
        ], JSON_PRETTY_PRINT);

        File::put($path, $payload);
        $this->info("Metrics exported to: $path");
    }

    private function findBottleneckEntry(array $stats): ?array
    {
        if (empty($stats)) {
            return null;
        }

        $sorted = [];
        foreach ($stats as $endpoint => $s) {
            $sorted[] = ['endpoint' => $endpoint] + $s;
        }
        usort($sorted, fn($a, $b) => $b['avg_ms'] <=> $a['avg_ms']);

        $top = $sorted[0];

        return [
            'endpoint' => $top['endpoint'],
            'avg_ms'   => round($top['avg_ms'], 2),
            'p95_ms'   => round($top['p95_ms'], 2),
            'module'   => $top['module'],
        ];
    }
}