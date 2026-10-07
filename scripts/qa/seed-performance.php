<?php

use App\Models\MeasurementTemplate;
use App\Models\PaymentMethod;
use App\Models\Product;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = config('database.default');
$database = (string) config("database.connections.$connection.database");
if (! app()->environment('testing') || ! preg_match('/(benchmark|performance)/i', $database)) {
    throw new RuntimeException('Refusing to reset data. Use APP_ENV=testing and a disposable database whose name/path contains benchmark or performance.');
}

Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);

/** @param list<array<string, mixed>> $rows */
function insertChunks(string $table, array $rows, int $size = 500): void
{
    foreach (array_chunk($rows, $size) as $chunk) {
        DB::table($table)->insert($chunk);
    }
}

function resetSequence(string $table): void
{
    $driver = DB::getDriverName();
    $max = (int) DB::table($table)->max('id');
    if ($max < 1) {
        return;
    }
    if ($driver === 'pgsql') {
        DB::select("SELECT setval(pg_get_serial_sequence(?, 'id')::regclass, ?, true)", [$table, $max]);
    } elseif ($driver === 'sqlite') {
        DB::table('sqlite_sequence')->updateOrInsert(['name' => $table], ['seq' => $max]);
    }
}

function benchmarkFields(int $count): array
{
    $fields = [];
    for ($i = 1; $i <= $count; $i++) {
        $fields[] = [
            'code' => 'measure_'.$i,
            'label' => 'Measure '.$i,
            'section' => 'Benchmark',
            'sequence' => $i - 1,
            'type' => 'numeric',
            'unit' => 'inches',
            'required' => false,
            'min' => null,
            'max' => null,
            'help' => '',
            'options' => [],
        ];
    }

    return $fields;
}

$templateFour = MeasurementTemplate::create([
    'code' => 'benchmark_four',
    'name' => 'Benchmark four-field template',
    'unit' => 'inches',
    'fields' => benchmarkFields(4),
]);
$templateThree = MeasurementTemplate::create([
    'code' => 'benchmark_three',
    'name' => 'Benchmark three-field template',
    'unit' => 'inches',
    'fields' => benchmarkFields(3),
]);
$products = [
    Product::create(['name' => 'Benchmark service A', 'template_id' => $templateFour->id, 'price' => 10000, 'cost_known' => true, 'cost' => 3000, 'taxes' => []]),
    Product::create(['name' => 'Benchmark service B', 'template_id' => $templateThree->id, 'price' => 10000, 'cost_known' => true, 'cost' => 3000, 'taxes' => []]),
    Product::create(['name' => 'Benchmark service C', 'template_id' => $templateThree->id, 'price' => 10000, 'cost_known' => true, 'cost' => 3000, 'taxes' => []]),
];
$cashMethod = PaymentMethod::where('kind', 'cash')->firstOrFail();
$now = now()->utc();

$customers = [];
for ($id = 1; $id <= 1000; $id++) {
    $phone = '+92310'.str_pad((string) $id, 7, '0', STR_PAD_LEFT);
    $customers[] = [
        'id' => $id,
        'name' => 'Benchmark Customer '.str_pad((string) $id, 4, '0', STR_PAD_LEFT),
        'phone' => $phone,
        'normalized_phone' => $phone,
        'whatsapp' => null,
        'normalized_whatsapp' => null,
        'address' => 'Benchmark fixture only',
        'notes' => null,
        'active' => true,
        'version' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ];
}
insertChunks('customers', $customers);
resetSequence('customers');

$orders = [];
$lines = [];
$snapshots = [];
$lineId = 0;
$snapshotId = 0;
$lineTotals = ['gross' => 10000, 'line_discount' => 0, 'header_discount' => 0, 'net' => 10000, 'tax' => 0, 'total' => 10000, 'taxes' => []];
$orderTotals = ['gross' => 30000, 'discount' => 0, 'net' => 30000, 'tax' => 0, 'total' => 30000];

for ($orderId = 1; $orderId <= 10000; $orderId++) {
    $customerId = (($orderId - 1) % 1000) + 1;
    $ordered = $now->copy()->subDays(370 - ($orderId % 365))->subMinutes($orderId % 1440);
    $promised = $ordered->copy()->addDays(7);
    $stagePool = $orderId <= 4000 ? ['New', 'Cutting', 'Stitching', 'Ready', 'Delivered'] : ['New', 'Cutting', 'Stitching', 'Ready'];
    $stage = $stagePool[$orderId % count($stagePool)];
    $delivered = $stage === 'Delivered' ? $ordered->copy()->addDays(6) : null;
    $contact = ['name' => 'Benchmark Customer '.str_pad((string) $customerId, 4, '0', STR_PAD_LEFT), 'phone' => '+92310'.str_pad((string) $customerId, 7, '0', STR_PAD_LEFT), 'whatsapp' => null, 'address' => 'Benchmark fixture only'];
    $orders[] = [
        'id' => $orderId,
        'reference' => 'PERF-ST-'.str_pad((string) $orderId, 5, '0', STR_PAD_LEFT),
        'customer_id' => $customerId,
        'source_order_id' => null,
        'ordered_at' => $ordered,
        'promised_at' => $promised,
        'original_promised_at' => $promised,
        'confirmed_at' => $ordered,
        'ready_at' => in_array($stage, ['Ready', 'Delivered'], true) ? $ordered->copy()->addDays(5) : null,
        'delivered_at' => $delivered,
        'cancelled_at' => null,
        'stage' => $stage,
        'contact' => json_encode($contact, JSON_THROW_ON_ERROR),
        'notes' => null,
        'internal_notes' => null,
        'discount_type' => 'fixed',
        'discount_value' => 0,
        'totals' => json_encode($orderTotals, JSON_THROW_ON_ERROR),
        'version' => 1,
        'created_at' => $ordered,
        'updated_at' => $ordered,
    ];

    foreach ($products as $index => $product) {
        $lineId++;
        $template = $index === 0 ? $templateFour : $templateThree;
        $lines[] = [
            'id' => $lineId,
            'order_id' => $orderId,
            'product_id' => $product->id,
            'template_id' => $template->id,
            'description' => $product->name,
            'category' => 'Benchmark',
            'quantity' => 1,
            'unit_price' => 10000,
            'offered_price' => 10000,
            'price_source' => 'product',
            'discount_type' => 'fixed',
            'discount_value' => 0,
            'taxes' => json_encode([], JSON_THROW_ON_ERROR),
            'totals' => json_encode($lineTotals, JSON_THROW_ON_ERROR),
            'cost_known' => true,
            'cost' => 3000,
            'measurement_mode' => 'shared',
            'instructions' => null,
            'version' => 1,
            'created_at' => $ordered,
            'updated_at' => $ordered,
        ];
        $snapshotId++;
        $snapshotFields = array_map(fn (array $field) => [...$field, 'value' => '40', 'is_set' => true], $template->fields);
        $snapshots[] = [
            'id' => $snapshotId,
            'order_line_id' => $lineId,
            'piece' => 0,
            'revision' => 1,
            'current' => true,
            'source_snapshot_id' => null,
            'supersedes_id' => null,
            'template_id' => $template->id,
            'template_name' => $template->name,
            'template_revision' => 1,
            'unit' => 'inches',
            'fields' => json_encode($snapshotFields, JSON_THROW_ON_ERROR),
            'frozen_at' => $ordered,
            'reason' => null,
            'version' => 1,
            'created_at' => $ordered,
            'updated_at' => $ordered,
        ];
    }

    if (count($orders) >= 250) {
        insertChunks('orders', $orders);
        insertChunks('order_lines', $lines);
        insertChunks('measurement_snapshots', $snapshots);
        $orders = $lines = $snapshots = [];
    }
}
insertChunks('orders', $orders);
insertChunks('order_lines', $lines);
insertChunks('measurement_snapshots', $snapshots);
resetSequence('orders');
resetSequence('order_lines');
resetSequence('measurement_snapshots');

$invoices = [];
$payments = [];
$settlements = [];
$allocations = [];
for ($invoiceId = 1; $invoiceId <= 7000; $invoiceId++) {
    $customerId = (($invoiceId - 1) % 1000) + 1;
    $ordered = $now->copy()->subDays(370 - ($invoiceId % 365))->subMinutes($invoiceId % 1440);
    $effective = $ordered->copy()->addDay();
    $contact = ['name' => 'Benchmark Customer '.str_pad((string) $customerId, 4, '0', STR_PAD_LEFT), 'phone' => '+92310'.str_pad((string) $customerId, 7, '0', STR_PAD_LEFT), 'address' => 'Benchmark fixture only'];
    $frozenLines = [];
    foreach ($products as $index => $product) {
        $frozenLines[] = ['id' => (($invoiceId - 1) * 3) + $index + 1, 'description' => $product->name, 'category' => 'Benchmark', 'quantity' => 1, 'unit_price' => 10000, 'offered_price' => 10000, 'price_source' => 'product', 'discount_type' => 'fixed', 'discount_value' => 0, 'taxes' => [], 'totals' => $lineTotals, 'instructions' => ''];
    }
    $invoices[] = [
        'id' => $invoiceId,
        'reference' => 'PERF-INV-'.str_pad((string) $invoiceId, 5, '0', STR_PAD_LEFT),
        'order_id' => $invoiceId,
        'customer_id' => $customerId,
        'currency' => 'PKR',
        'state' => 'posted',
        'effective_at' => $effective,
        'contact' => json_encode($contact, JSON_THROW_ON_ERROR),
        'lines' => json_encode($frozenLines, JSON_THROW_ON_ERROR),
        'totals' => json_encode($orderTotals, JSON_THROW_ON_ERROR),
        'version' => 1,
        'created_at' => $effective,
        'updated_at' => $effective,
    ];
    if ($invoiceId <= 5000) {
        $paymentAt = $effective->copy()->addDay();
        $amount = $invoiceId <= 4000 ? 30000 : 15000;
        $payments[] = [
            'id' => $invoiceId,
            'reference' => 'PERF-PAY-'.str_pad((string) $invoiceId, 5, '0', STR_PAD_LEFT),
            'customer_id' => $customerId,
            'payment_method_id' => $cashMethod->id,
            'amount' => $amount,
            'effective_at' => $paymentAt,
            'external_reference' => null,
            'settlement' => 'settled',
            'contact' => json_encode($contact, JSON_THROW_ON_ERROR),
            'method_snapshot' => json_encode(['name' => $cashMethod->name, 'kind' => $cashMethod->kind], JSON_THROW_ON_ERROR),
            'version' => 1,
            'created_at' => $paymentAt,
            'updated_at' => $paymentAt,
        ];
        $settlements[] = [
            'id' => $invoiceId,
            'payment_id' => $invoiceId,
            'state' => 'settled',
            'effective_at' => $paymentAt,
            'reason' => null,
            'version' => 1,
            'created_at' => $paymentAt,
            'updated_at' => $paymentAt,
        ];
        $allocations[] = [
            'id' => $invoiceId,
            'payment_id' => $invoiceId,
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'effective_at' => $paymentAt,
            'reverses_id' => null,
            'reason' => null,
            'version' => 1,
            'created_at' => $paymentAt,
            'updated_at' => $paymentAt,
        ];
    }
    if (count($invoices) >= 500) {
        insertChunks('invoices', $invoices);
        insertChunks('payments', $payments);
        insertChunks('settlements', $settlements);
        insertChunks('allocations', $allocations);
        $invoices = $payments = $settlements = $allocations = [];
    }
}
insertChunks('invoices', $invoices);
insertChunks('payments', $payments);
insertChunks('settlements', $settlements);
insertChunks('allocations', $allocations);
resetSequence('invoices');
resetSequence('payments');
resetSequence('settlements');
resetSequence('allocations');

$credits = [];
$creditAllocations = [];
$refunds = [];
for ($creditId = 1; $creditId <= 500; $creditId++) {
    $invoiceId = $creditId <= 250 ? $creditId : 4750 + $creditId;
    $customerId = (($invoiceId - 1) % 1000) + 1;
    $effective = $now->copy()->subDays(370 - ($invoiceId % 365))->subMinutes($invoiceId % 1440)->addDays(3);
    $credits[] = [
        'id' => $creditId,
        'reference' => 'PERF-CR-'.str_pad((string) $creditId, 5, '0', STR_PAD_LEFT),
        'invoice_id' => $invoiceId,
        'customer_id' => $customerId,
        'lines' => json_encode([['invoice_line_id' => (($invoiceId - 1) * 3) + 1, 'quantity' => 1]], JSON_THROW_ON_ERROR),
        'amount' => 5000,
        'net' => 5000,
        'tax' => 0,
        'effective_at' => $effective,
        'reason' => 'Benchmark correction',
        'version' => 1,
        'created_at' => $effective,
        'updated_at' => $effective,
    ];
    if ($creditId <= 250) {
        $refunds[] = [
            'id' => $creditId,
            'reference' => 'PERF-RF-'.str_pad((string) $creditId, 5, '0', STR_PAD_LEFT),
            'credit_id' => $creditId,
            'payment_method_id' => $cashMethod->id,
            'amount' => 2000,
            'effective_at' => $effective->copy()->addHour(),
            'reason' => 'Benchmark refund',
            'version' => 1,
            'created_at' => $effective->copy()->addHour(),
            'updated_at' => $effective->copy()->addHour(),
        ];
    } else {
        $creditAllocations[] = [
            'id' => $creditId - 250,
            'credit_id' => $creditId,
            'invoice_id' => $invoiceId,
            'amount' => 5000,
            'effective_at' => $effective,
            'version' => 1,
            'created_at' => $effective,
            'updated_at' => $effective,
        ];
    }
}
insertChunks('credits', $credits);
insertChunks('credit_allocations', $creditAllocations);
insertChunks('refunds', $refunds);
resetSequence('credits');
resetSequence('credit_allocations');
resetSequence('refunds');

DB::table('number_sequences')->where('kind', 'ST')->update(['next_number' => 10001]);
DB::table('number_sequences')->where('kind', 'INV')->update(['next_number' => 7001]);
DB::table('number_sequences')->where('kind', 'PAY')->update(['next_number' => 5001]);
DB::table('number_sequences')->where('kind', 'CR')->update(['next_number' => 501]);
DB::table('number_sequences')->where('kind', 'RF')->update(['next_number' => 251]);
DB::table('business_settings')->where('id', 1)->update(['cost_reporting' => true]);

$measurementValues = 10000 * (4 + 3 + 3);
printf(
    "Performance fixture ready in isolated database %s: %d orders, %d order lines, %d measurement values, %d posted invoices, %d payments.\n",
    $database,
    DB::table('orders')->count(),
    DB::table('order_lines')->count(),
    $measurementValues,
    DB::table('invoices')->count(),
    DB::table('payments')->count(),
);
