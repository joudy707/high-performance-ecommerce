<?php

// ─── Configuration ────────────────────────────────────────────────────────────

$config = [
    'base_ports'       => [8001, 8002, 8003],
    'scale_ports'      => range(8004, 8099),
    'scale_up_threshold'   => 25,
    'scale_down_threshold' => 10,
    'check_interval'   => 5,
    'live_interval'    => 1,
    'nginx_root'       => 'C:\\nginx',
    'nginx_conf'       => 'C:\\nginx\\conf\\nginx.conf',
    'nginx_status_url' => 'http://127.0.0.1:8080/nginx_status',
    'project_path'     => 'D:\\جامعة\\مشاريع\\PP\\high-performance-ecommerce',
];

// ─── State ────────────────────────────────────────────────────────────────────

$activeScaleInstances = [];
$lastEvent = '';

// ─── Helpers ──────────────────────────────────────────────────────────────────

function getTotalConnections(string $url): int
{
    $status = @file_get_contents($url);
    if (!$status) return 0;
    preg_match('/Active connections:\s+(\d+)/', $status, $matches);
    return (int)($matches[1] ?? 0);
}

function getLoadPerServer(int $totalConns, int $serverCount): int
{
    if ($serverCount === 0) return 0;
    return (int)ceil($totalConns / $serverCount);
}

function isPortActive(int $port): bool
{
    return @fsockopen('127.0.0.1', $port, $errno, $errstr, 1) !== false;
}

function spawnInstance(int $port, string $projectPath): int|false
{
    $cmd = "start /B php artisan serve --port={$port}";
    $process = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $projectPath);
    if (is_resource($process)) {
        $status = proc_get_status($process);
        return $status['pid'];
    }
    return false;
}

function killInstance(int $port): void
{
    $output = shell_exec("netstat -ano | findstr :{$port}");
    if ($output && preg_match('/\s+(\d+)\s*$/', trim($output), $matches)) {
        shell_exec("taskkill /PID {$matches[1]} /F 2>NUL");
    }
}

function updateNginxConfig(array $activePorts, string $nginxConf, string $nginxRoot): void
{
    $logDir = rtrim($nginxRoot, '\\') . '\\logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0777, true);

    $config = "worker_processes 1;\n\n";
    $config .= "events { worker_connections 1024; }\n\n";
    $config .= "http {\n";
    $logDirFwd = str_replace('\\', '/', $logDir);
    $config .= "    error_log \"{$logDirFwd}/error.log\";\n";
    $config .= "    access_log \"{$logDirFwd}/access.log\";\n\n";
    $config .= "    upstream laravel_servers {\n";
    $config .= "        least_conn;\n";
    foreach ($activePorts as $port) {
        $config .= "        server 127.0.0.1:{$port};\n";
    }
    $config .= "    }\n\n";
    $config .= "    server {\n";
    $config .= "        listen 8080;\n\n";
    $config .= "        location /nginx_status {\n";
    $config .= "            stub_status on;\n";
    $config .= "            access_log off;\n";
    $config .= "            allow 127.0.0.1;\n";
    $config .= "            deny all;\n";
    $config .= "        }\n\n";
    $config .= "        location / {\n";
    $config .= "            proxy_pass http://laravel_servers;\n";
    $config .= "            proxy_set_header Host \$host;\n";
    $config .= "            proxy_set_header X-Real-IP \$remote_addr;\n";
    $config .= "        }\n";
    $config .= "    }\n";
    $config .= "}\n";

    file_put_contents($nginxConf, $config);
    $nginxExe = rtrim($nginxRoot, '\\') . '\\nginx.exe';
    shell_exec("\"{$nginxExe}\" -p \"{$nginxRoot}\" -s reload");
}

function clearScreen(): void
{
    system('cls');
}

// ─── Main Loop ────────────────────────────────────────────────────────────────

clearScreen();
echo "╔══════════════════════════════════════════╗\n";
echo "║   Auto Scaling Monitor (Dynamic Logic)   ║\n";
echo "╚══════════════════════════════════════════╝\n";
echo "\nScale UP threshold:   > {$config['scale_up_threshold']} connections/server\n";
echo "Scale DOWN threshold: ≤ {$config['scale_down_threshold']} connections/server\n";
echo "Scale decision every {$config['check_interval']}s | Live view every {$config['live_interval']}s\n";
echo "Press Ctrl+C to stop.\n";
echo str_repeat('─', 60) . "\n";

$lastScaleCheck = time();
$lastPrintTime = 0;

while (true) {
    $now = time();

    // Scale decision every N seconds
    if ($now - $lastScaleCheck >= $config['check_interval']) {
        $totalConns = getTotalConnections($config['nginx_status_url']);
        $activePorts = $config['base_ports'];
        foreach ($activeScaleInstances as $port) {
            $activePorts[] = $port;
        }

        $serverCount = count($activePorts);
        $perServer = getLoadPerServer($totalConns, $serverCount);

        // Scale UP
        if ($perServer > $config['scale_up_threshold']) {
            $available = array_diff($config['scale_ports'], $activeScaleInstances);
            if (!empty($available)) {
                $newPort = array_values($available)[0];
                $pid = spawnInstance($newPort, $config['project_path']);
                if ($pid) {
                    $activeScaleInstances[] = $newPort;
                    $activePorts[] = $newPort;
                    updateNginxConfig($activePorts, $config['nginx_conf'], $config['nginx_root']);
                    $lastEvent = "SCALE UP → port {$newPort} (PID: {$pid})";
                }
            } else {
                $lastEvent = "SCALE UP needed but max reached (3 dynamic servers)";
            }

            // Scale DOWN
        } elseif ($perServer <= $config['scale_down_threshold'] && !empty($activeScaleInstances)) {
            $portToKill = array_pop($activeScaleInstances);
            killInstance($portToKill);
            $activePorts = array_values(array_diff($activePorts, [$portToKill]));
            updateNginxConfig($activePorts, $config['nginx_conf'], $config['nginx_root']);
            $lastEvent = "SCALE DOWN → killed port {$portToKill}";
        }

        $lastScaleCheck = $now;
    }

    // Live metrics every 1 second
    $nowMicro = microtime(true);
    if ($nowMicro - ($lastPrintTime ?: 0) >= $config['live_interval']) {
        $totalConns = getTotalConnections($config['nginx_status_url']);
        $activePorts = array_merge($config['base_ports'], $activeScaleInstances);
        $serverCount = count($activePorts);
        $perServer = getLoadPerServer($totalConns, $serverCount);

        clearScreen();
        echo "══════════════════════════════════════════\n";
        echo "\nThresholds: UP > {$config['scale_up_threshold']} | DOWN ≤ {$config['scale_down_threshold']} conns/server\n";
        echo "Scale decision every {$config['check_interval']}s | Live view every 1s\n";
        echo str_repeat('─', 60) . "\n";

        $time = date('H:i:s');
        $ports = implode(', ', $activePorts);
        $barLength = min(20, max(0, (int)ceil($perServer / 5)));
        $bar = str_repeat('█', $barLength) . str_repeat('░', 20 - $barLength);

        echo "\n[{$time}] Total: {$totalConns} conns | Servers: {$serverCount} | Per server: {$perServer}\n";
        echo "Load: [{$bar}]\n";
        echo "Active servers: {$ports}\n";

        if ($lastEvent) {
            echo "\n⚡ Last event: {$lastEvent}\n";
        }

        echo "\n--- Press Ctrl+C to stop ---\n";

        $lastPrintTime = $nowMicro;
    }

    sleep($config['live_interval']);
}
