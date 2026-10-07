<?php

use App\Domain\Documents;
use App\Domain\Reports;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = config('database.default');
$database = (string) config("database.connections.$connection.database");
if (! app()->environment('testing') || ! preg_match('/(benchmark|performance)/i', $database)) {
    throw new RuntimeException('Run benchmarks only with APP_ENV=testing against a disposable database whose name/path contains benchmark or performance.');
}
if (DB::table('orders')->count() < 10000 || DB::table('order_lines')->count() < 30000) {
    throw new RuntimeException('Performance fixture is missing or incomplete. Run scripts/qa/seed-performance.php first.');
}

$queryCount = 0;
$queryMs = 0.0;
DB::listen(function (QueryExecuted $query) use (&$queryCount, &$queryMs): void {
    $queryCount++;
    $queryMs += $query->time;
});

/** @return array<string, int|float|string> */
function measureReport(string $type, array $filters = []): array
{
    global $queryCount, $queryMs;
    gc_collect_cycles();
    $queryCount = 0;
    $queryMs = 0.0;
    $before = memory_get_usage(true);
    $start = hrtime(true);
    $rows = Reports::rows($type, $filters);
    $elapsedMs = (hrtime(true) - $start) / 1_000_000;

    return [
        'report' => $type,
        'rows' => count($rows),
        'queries' => $queryCount,
        'database_ms' => round($queryMs, 2),
        'elapsed_ms' => round($elapsedMs, 2),
        'memory_delta_mb' => round((memory_get_usage(true) - $before) / 1048576, 2),
        'peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
    ];
}

$results = [];
foreach (['orders', 'items', 'customers', 'payments', 'outstanding', 'credits', 'profit', 'daily'] as $type) {
    $results[] = measureReport($type);
}

$exportRows = Reports::rows('items', []);
foreach (['csv', 'xlsx'] as $format) {
    gc_collect_cycles();
    $start = hrtime(true);
    $path = Documents::writeExport($exportRows, 'benchmark-items', $format);
    $elapsedMs = (hrtime(true) - $start) / 1_000_000;
    $bytes = Storage::disk('local')->size($path);
    Storage::disk('local')->delete($path);
    $results[] = [
        'report' => 'items-'.$format.'-export',
        'rows' => count($exportRows),
        'queries' => 0,
        'database_ms' => 0,
        'elapsed_ms' => round($elapsedMs, 2),
        'memory_delta_mb' => 0,
        'peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
        'bytes' => $bytes,
    ];
}

$output = [
    'recorded_at' => now()->toIso8601String(),
    'database' => $database,
    'driver' => DB::getDriverName(),
    'php' => PHP_VERSION,
    'os' => php_uname(),
    'memory_limit' => ini_get('memory_limit'),
    'fixture' => [
        'orders' => DB::table('orders')->count(),
        'order_lines' => DB::table('order_lines')->count(),
        'measurement_values' => 100000,
        'invoices' => DB::table('invoices')->count(),
        'payments' => DB::table('payments')->count(),
    ],
    'results' => $results,
];

$json = json_encode($output, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
file_put_contents('/tmp/stitching-benchmark-results.json', $json."\n");
echo $json."\n";
fwrite(STDERR, "Saved metrics to /tmp/stitching-benchmark-results.json\n");
