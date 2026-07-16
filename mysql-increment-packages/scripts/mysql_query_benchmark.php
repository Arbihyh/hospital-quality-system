<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$options = getopt('', [
    'iterations::',
    'warmup::',
    'sample-size::',
    'mode::',
    'page-size::',
    'progress::',
]);

$iterations = max(1, (int)($options['iterations'] ?? 100000));
$warmup = max(0, (int)($options['warmup'] ?? 1000));
$sampleSize = max(1, (int)($options['sample-size'] ?? 100));
$pageSize = max(1, (int)($options['page-size'] ?? 10));
$mode = (string)($options['mode'] ?? 'patient-by-id');
$showProgress = !isset($options['progress']) || $options['progress'] !== '0';

function benchmark_now_ms(): float
{
    return hrtime(true) / 1000000;
}

function percentile(array $values, float $percentile): float
{
    if (!$values) {
        return 0.0;
    }
    sort($values, SORT_NUMERIC);
    $index = (int)ceil(($percentile / 100) * count($values)) - 1;
    $index = max(0, min(count($values) - 1, $index));
    return $values[$index];
}

function format_ms(float $value): string
{
    return number_format($value, 4, '.', '');
}

function run_patient_by_id_query(array $ids, int $index): void
{
    $id = $ids[$index % count($ids)];
    DB::table('patient_info')
        ->select('MED_REC_ID', 'AAA28', 'AAA29', 'AAC01')
        ->where('MED_REC_ID', $id)
        ->limit(1)
        ->first();
}

function run_list_page_query(int $pageSize): void
{
    DB::table('patient_info')
        ->select('MED_REC_ID', 'AAA28', 'AAA29', 'AAC01')
        ->orderBy('AAC01', 'desc')
        ->limit($pageSize)
        ->get();
}

$connection = DB::connection()->getName();
$database = DB::connection()->getDatabaseName();
$driver = DB::connection()->getDriverName();

$ids = DB::table('patient_info')
    ->whereNotNull('MED_REC_ID')
    ->orderBy('AAC01', 'desc')
    ->limit($sampleSize)
    ->pluck('MED_REC_ID')
    ->filter(static function ($value) {
        return $value !== null && $value !== '';
    })
    ->values()
    ->all();

if ($mode === 'patient-by-id' && !$ids) {
    fwrite(STDERR, "No MED_REC_ID sample found in patient_info.\n");
    exit(1);
}

if (!in_array($mode, ['patient-by-id', 'list-page'], true)) {
    fwrite(STDERR, "Unsupported mode: {$mode}. Use patient-by-id or list-page.\n");
    exit(1);
}

$startedAt = date('Y-m-d H:i:s');
echo "MySQL query benchmark\n";
echo "Started at: {$startedAt}\n";
echo "Connection: {$connection}\n";
echo "Driver: {$driver}\n";
echo "Database: {$database}\n";
echo "Mode: {$mode}\n";
echo "Iterations: {$iterations}\n";
echo "Warmup: {$warmup}\n";
echo "Sample size: " . count($ids) . "\n";
if ($mode === 'list-page') {
    echo "Page size: {$pageSize}\n";
}
echo str_repeat('-', 48) . "\n";

for ($i = 0; $i < $warmup; $i++) {
    if ($mode === 'patient-by-id') {
        run_patient_by_id_query($ids, $i);
    } else {
        run_list_page_query($pageSize);
    }
}

$durations = [];
$totalStart = benchmark_now_ms();
$progressEvery = max(1, (int)floor($iterations / 10));

for ($i = 0; $i < $iterations; $i++) {
    $queryStart = benchmark_now_ms();
    if ($mode === 'patient-by-id') {
        run_patient_by_id_query($ids, $i);
    } else {
        run_list_page_query($pageSize);
    }
    $durations[] = benchmark_now_ms() - $queryStart;

    if ($showProgress && (($i + 1) % $progressEvery === 0 || $i + 1 === $iterations)) {
        echo "Progress: " . ($i + 1) . "/{$iterations}\n";
    }
}

$totalMs = benchmark_now_ms() - $totalStart;
$totalSeconds = $totalMs / 1000;
$sum = array_sum($durations);
$min = min($durations);
$max = max($durations);
$avg = $sum / count($durations);
$qps = $iterations / max($totalSeconds, 0.000001);

$result = [
    'mode' => $mode,
    'iterations' => $iterations,
    'warmup' => $warmup,
    'total_seconds' => round($totalSeconds, 6),
    'qps' => round($qps, 2),
    'avg_ms' => round($avg, 6),
    'min_ms' => round($min, 6),
    'p50_ms' => round(percentile($durations, 50), 6),
    'p95_ms' => round(percentile($durations, 95), 6),
    'p99_ms' => round(percentile($durations, 99), 6),
    'max_ms' => round($max, 6),
];

echo str_repeat('-', 48) . "\n";
echo "Total seconds: " . number_format($result['total_seconds'], 6, '.', '') . "\n";
echo "QPS: " . number_format($result['qps'], 2, '.', '') . "\n";
echo "Avg ms/query: " . format_ms($result['avg_ms']) . "\n";
echo "Min ms/query: " . format_ms($result['min_ms']) . "\n";
echo "P50 ms/query: " . format_ms($result['p50_ms']) . "\n";
echo "P95 ms/query: " . format_ms($result['p95_ms']) . "\n";
echo "P99 ms/query: " . format_ms($result['p99_ms']) . "\n";
echo "Max ms/query: " . format_ms($result['max_ms']) . "\n";
echo "JSON: " . json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
