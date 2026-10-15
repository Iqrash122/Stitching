<?php

require 'c:/laragon/www/stitching-studio/vendor/autoload.php';
$app = require 'c:/laragon/www/stitching-studio/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Domain\Billing;
use App\Domain\Orders;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\StitchingOrder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

echo '=== Deliver Test — ST-00002 ==='.PHP_EOL;
echo '================================'.PHP_EOL.PHP_EOL;

$order = StitchingOrder::with('lines')->find(2);
echo '[INFO] Order:  '.$order->reference.' | Stage: '.$order->stage.PHP_EOL;

$invoice = Invoice::where('order_id', 2)->first();
echo '[INFO] Invoice: '.$invoice->reference.' | State: '.$invoice->state.' | Total: Rs '.number_format(($invoice->totals['total'] ?? 0) / 100, 2).PHP_EOL.PHP_EOL;

// ── STEP 1: Post (agar draft ho) ────────────────────────────
if ($invoice->state === 'draft') {
    echo '[STEP 1] Invoice post karo...'.PHP_EOL;
    try {
        Billing::post($invoice->id, [
            'key' => Str::uuid()->toString(),
            'version' => $invoice->version,
            'effective_at' => now()->toDateString(),
        ]);
        $invoice->refresh();
        echo '  State: '.$invoice->state.' ✅'.PHP_EOL;
    } catch (Exception $e) {
        echo '  ERROR: '.$e->getMessage().PHP_EOL;
        exit(1);
    }
} else {
    echo '[STEP 1] Invoice already '.$invoice->state.' — skip.'.PHP_EOL;
}

// ── STEP 2: Balance ──────────────────────────────────────────
$due = Billing::balance($invoice);
echo '[STEP 2] Outstanding Balance: Rs '.number_format($due / 100, 2).PHP_EOL.PHP_EOL;

// ── STEP 3: Cash payment ─────────────────────────────────────
if ($due > 0) {
    echo '[STEP 3] Cash payment record karo (Rs '.number_format($due / 100, 2).')...'.PHP_EOL;
    try {
        $cashMethod = PaymentMethod::where('kind', 'cash')->first();
        Billing::payment([
            'key' => Str::uuid()->toString(),
            'customer_id' => $order->customer_id,
            'payment_method_id' => $cashMethod->id,
            'amount' => bcdiv((string) $due, '100', 2),
            'settlement' => 'settled',
            'effective_at' => now()->toDateString(),
            'external_reference' => 'TEST-CASH-001',
            'invoice_id' => $invoice->id,
            'invoice_version' => $invoice->version,
        ]);
        $invoice->refresh();
        $newDue = Billing::balance($invoice);
        echo '  Remaining Balance: Rs '.number_format($newDue / 100, 2).' ✅'.PHP_EOL.PHP_EOL;
    } catch (Exception $e) {
        echo '  ERROR: '.$e->getMessage().PHP_EOL;
        exit(1);
    }
} else {
    echo '[STEP 3] Balance already zero — skip.'.PHP_EOL.PHP_EOL;
}

// ── STEP 4: DELIVER ──────────────────────────────────────────
echo '[STEP 4] Order DELIVER karo...'.PHP_EOL;
$order->refresh();
try {
    Orders::transition($order->id, [
        'version' => $order->version,
        'action' => 'Delivered',
        'reason' => null,
        'promised_at' => null,
    ]);
    $order->refresh();
    echo '  Stage:        '.$order->stage.' ✅'.PHP_EOL;
    echo '  Delivered At: '.$order->delivered_at.PHP_EOL;
} catch (Exception $e) {
    echo '  ERROR: '.$e->getMessage().PHP_EOL;
    exit(1);
}

echo PHP_EOL;
echo '================================'.PHP_EOL;
echo '[FINAL SUMMARY]'.PHP_EOL;
$invoice->refresh();
echo '  Order:         '.$order->reference.PHP_EOL;
echo '  Stage:         '.$order->stage.' ✅'.PHP_EOL;
echo '  Invoice:       '.$invoice->reference.' ('.$invoice->state.')'.PHP_EOL;
echo '  Invoice Total: Rs '.number_format(($invoice->totals['total'] ?? 0) / 100, 2).PHP_EOL;
echo '  Balance Due:   Rs '.number_format(Billing::balance($invoice) / 100, 2).PHP_EOL;
echo '  Delivered At:  '.$order->delivered_at.PHP_EOL;
echo '  URL:           http://127.0.0.1:8000/studio/orders/'.$order->id.PHP_EOL;
echo '================================'.PHP_EOL;
echo PHP_EOL.'DELIVER TEST MUKAMMAL! ✅'.PHP_EOL;
