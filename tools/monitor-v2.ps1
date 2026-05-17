param(
    [string]$Out = "metrics.csv",
    [int]$Seconds = 70
)

$ErrorActionPreference = "SilentlyContinue"
$cores = [Environment]::ProcessorCount
$names = @("php", "mysqld", "httpd")
$previousCpu = @{}
$previousTime = Get-Date
$start = Get-Date

"timestamp,php_cpu,mysql_cpu,apache_cpu,total_app_cpu,app_memory_mb,system_cpu" | Out-File -Encoding utf8 $Out

while (((Get-Date) - $start).TotalSeconds -lt $Seconds) {
    $now = Get-Date
    $elapsed = ($now - $previousTime).TotalSeconds
    if ($elapsed -le 0) { $elapsed = 1 }

    $cpuByName = @{ php = 0.0; mysqld = 0.0; httpd = 0.0 }
    $memMb = 0.0

    foreach ($name in $names) {
        $procs = Get-Process -Name $name
        $cpuSeconds = ($procs | Measure-Object CPU -Sum).Sum
        $memBytes = ($procs | Measure-Object WorkingSet64 -Sum).Sum

        if ($null -eq $cpuSeconds) { $cpuSeconds = 0 }
        if ($null -eq $memBytes) { $memBytes = 0 }

        if ($previousCpu.ContainsKey($name)) {
            $cpuPct = (($cpuSeconds - $previousCpu[$name]) / $elapsed / $cores) * 100
            if ($cpuPct -lt 0) { $cpuPct = 0 }
            $cpuByName[$name] = [Math]::Round($cpuPct, 2)
        }

        $previousCpu[$name] = $cpuSeconds
        $memMb += $memBytes / 1MB
    }

    $processor = Get-CimInstance Win32_PerfFormattedData_PerfOS_Processor -Filter "Name='_Total'"
    $systemCpu = 100 - [double]$processor.PercentIdleTime
    if ($systemCpu -lt 0) { $systemCpu = 0 }
    if ($systemCpu -gt 100) { $systemCpu = 100 }

    $total = [Math]::Round($cpuByName.php + $cpuByName.mysqld + $cpuByName.httpd, 2)
    $line = "{0},{1},{2},{3},{4},{5},{6}" -f `
        $now.ToString("o"), `
        $cpuByName.php, `
        $cpuByName.mysqld, `
        $cpuByName.httpd, `
        $total, `
        ([Math]::Round($memMb, 2)), `
        ([Math]::Round($systemCpu, 2))

    Add-Content -Encoding utf8 $Out $line
    $previousTime = $now
    Start-Sleep -Seconds 1
}
