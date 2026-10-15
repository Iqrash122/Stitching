<?php

namespace App\Domain;

use App\Models\Allocation;
use App\Models\BusinessSetting;
use App\Models\Credit;
use App\Models\CreditAllocation;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Refund;
use App\Models\Settlement;
use App\Models\StitchingOrder;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class Billing
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string|int, mixed>
     */
    public static function once(string $key, array $payload, callable $action): array
    {
        return DB::transaction(function () use ($key, $payload, $action) {
            $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
            DB::table('idempotency_records')->insertOrIgnore(['key' => $key, 'hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
            $row = DB::table('idempotency_records')->where('key', $key)->lockForUpdate()->first();
            if ($row->hash !== $hash) {
                Pricing::fail('That request key was already used with different details.');
            }if ($row->result) {
                return json_decode($row->result, true);
            }$result = $action();
            DB::table('idempotency_records')->where('id', $row->id)->update(['result' => json_encode($result)]);

            return $result;
        }, 3);
    }

    public static function date(string $value): Carbon
    {
        $d = Orders::date($value);
        if ($d->gt(now())) {
            Pricing::fail('Financial dates cannot be in the future.');
        }

        return $d;
    }

    private static function chronological(Invoice $invoice, CarbonInterface $date): void
    {
        $latest = $invoice->effective_at;
        foreach ([Allocation::class, Credit::class, CreditAllocation::class] as $class) {
            $event = $class::where('invoice_id', $invoice->id)->orderByDesc('effective_at')->first();
            if ($event && $event->effective_at->gt($latest)) {
                $latest = $event->effective_at;
            }
        }
        $refund = Refund::whereIn('credit_id', Credit::select('id')->where('invoice_id', $invoice->id))->orderByDesc('effective_at')->first();
        if ($refund && $refund->effective_at->gt($latest)) {
            $latest = $refund->effective_at;
        }
        if ($date->lt($latest)) {
            Pricing::fail('Financial date precedes an existing transaction on this invoice. Use a date at or after the latest event.');
        }
    }

    public static function balance(Invoice $i, ?CarbonInterface $cutoff = null): int
    {
        $cutoff = $cutoff ?? now();
        if ($i->state !== 'posted' || $i->effective_at->gt($cutoff)) {
            return 0;
        }$cash = Allocation::where('invoice_id', $i->id)->where('effective_at', '<=', $cutoff)->sum('amount');
        $credits = CreditAllocation::where('invoice_id', $i->id)->where('effective_at', '<=', $cutoff)->sum('amount');

        return $i->totals['total'] - (int) $cash - (int) $credits;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string|int, mixed>
     */
    public static function invoice(int $orderId, array $data): array
    {
        return self::once($data['key'], ['op' => 'invoice', 'order' => $orderId, ...$data], function () use ($orderId) {
            $o = Orders::lock(StitchingOrder::class, $orderId);
            if (! $o->confirmed_at) {
                Pricing::fail('Confirm the order first.');
            }$existing = Invoice::where('order_id', $o->id)->first();
            if ($existing) {
                return ['id' => $existing->id];
            }Orders::active($o);
            $lines = $o->lines()->get()->map(fn ($l) => $l->only(['id', 'description', 'category', 'quantity', 'unit_price', 'offered_price', 'price_source', 'discount_type', 'discount_value', 'taxes', 'totals', 'instructions']))->all();
            $i = Invoice::create(['reference' => Orders::number('INV'), 'order_id' => $o->id, 'customer_id' => $o->customer_id, 'currency' => BusinessSetting::findOrFail(1)->currency, 'contact' => $o->contact, 'lines' => $lines, 'totals' => $o->totals]);
            Orders::audit($i, 'invoice_drafted');

            return ['id' => $i->id];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string|int, mixed>
     */
    public static function post(int $id, array $data): array
    {
        return self::once($data['key'], ['op' => 'post', 'invoice' => $id, ...$data], function () use ($id, $data) {
            $i = Orders::lock(Invoice::class, $id, $data['version']);
            if ($i->state !== 'draft') {
                Pricing::fail('Only a draft invoice can be posted.');
            }$o = StitchingOrder::findOrFail($i->order_id);
            if (! $o->confirmed_at || $i->totals !== $o->totals) {
                Pricing::fail('Invoice does not match the confirmed order.');
            }
            $sourceLines = $o->lines()->get()->map(fn ($l) => $l->only(['id', 'description', 'category', 'quantity', 'unit_price', 'offered_price', 'price_source', 'discount_type', 'discount_value', 'taxes', 'totals', 'instructions']))->all();
            $normalize = function ($v) use (&$normalize) {
                $v = json_decode(json_encode($v, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
                if (is_array($v)) {
                    ksort($v);
                    $v = array_map($normalize, $v);
                }

                return $v;
            };
            if ($i->customer_id !== $o->customer_id || $i->contact !== $o->contact || $normalize($i->lines) !== $normalize($sourceLines)) {
                Pricing::fail('Invoice customer or lines do not match the confirmed order.');
            }
            $d = self::date($data['effective_at']);
            if ($d->lt($o->confirmed_at)) {
                Pricing::fail('Invoice date must not precede order confirmation.');
            }$i->state = 'posted';
            $i->effective_at = $d;
            Orders::touch($i);
            Orders::audit($i, 'posted');

            return ['id' => $i->id];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string|int, mixed>
     */
    public static function payment(array $data): array
    {
        return self::once($data['key'], ['op' => 'payment', ...$data], function () use ($data) {
            $c = Orders::lock(Customer::class, $data['customer_id']);
            $method = PaymentMethod::where('active', true)->where('id', $data['payment_method_id'])->firstOrFail();
            $amount = Pricing::minor($data['amount']);
            if (! $amount) {
                Pricing::fail('Payment must be greater than zero.');
            }$d = self::date($data['effective_at']);
            $state = $method->kind === 'cash' ? 'settled' : $data['settlement'];
            $p = Payment::create(['reference' => Orders::number('PAY'), 'contact' => $c->only(['name', 'phone', 'address']), 'method_snapshot' => $method->only(['name', 'kind']), 'customer_id' => $c->id, 'payment_method_id' => $method->id, 'amount' => $amount, 'effective_at' => $d, 'external_reference' => $data['external_reference'] ?? '', 'settlement' => $state]);
            Settlement::create(['payment_id' => $p->id, 'state' => $state, 'effective_at' => $d]);
            if (! empty($data['invoice_id'])) {
                if ($state !== 'settled') {
                    Pricing::fail('Pending bank payments cannot settle invoices. Record them unallocated, then settle and allocate.');
                }self::allocatePayment($p, $data['invoice_id'], $amount, $data['invoice_version'], $d);
            }
            Orders::audit($p, 'payment_recorded', ['amount' => $amount, 'settlement' => $state]);

            return ['id' => $p->id];
        });
    }

    private static function allocatePayment(Payment $p, int $invoice, int $amount, int $version, CarbonInterface $date): void
    {
        $i = Orders::lock(Invoice::class, $invoice, $version);
        if ($i->state !== 'posted' || $i->customer_id != $p->customer_id || $p->settlement !== 'settled') {
            Pricing::fail('Payment and posted invoice must belong to the same customer and funds must be settled.');
        }if ($date->lt($i->effective_at) || $date->lt($p->effective_at)) {
            Pricing::fail('Allocation cannot precede its invoice or payment.');
        }$last = Settlement::where('payment_id', $p->id)->orderByDesc('effective_at')->first();
        if ($date->lt($last->effective_at)) {
            Pricing::fail('Allocation cannot precede bank settlement.');
        }self::chronological($i, $date);
        $available = $p->amount - (int) Allocation::where('payment_id', $p->id)->sum('amount');
        if ($amount <= 0 || $amount > $available || $amount > self::balance($i)) {
            Pricing::fail('Allocation exceeds available money or the current invoice balance. Refresh balances.');
        }Allocation::create(['payment_id' => $p->id, 'invoice_id' => $i->id, 'amount' => $amount, 'effective_at' => $date]);
        Orders::touch($i);
        Orders::audit($i, 'payment_allocated', ['payment' => $p->id, 'amount' => $amount]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string|int, mixed>
     */
    public static function allocate(int $payment, array $data): array
    {
        return self::once($data['key'], ['op' => 'allocate', 'payment' => $payment, ...$data], function () use ($payment, $data) {
            $p = Orders::lock(Payment::class, $payment, $data['version']);
            self::allocatePayment($p, $data['invoice_id'], Pricing::minor($data['amount']), $data['invoice_version'], self::date($data['effective_at']));
            Orders::touch($p);

            return ['id' => $p->id];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string|int, mixed>
     */
    public static function settle(int $payment, array $data): array
    {
        return self::once($data['key'], ['op' => 'settle', 'payment' => $payment, ...$data], function () use ($payment, $data) {
            $p = Orders::lock(Payment::class, $payment, $data['version']);
            if ($p->settlement !== 'pending') {
                Pricing::fail('Payment is already settled.');
            }$d = self::date($data['effective_at']);
            if ($d->lt($p->effective_at)) {
                Pricing::fail('Settlement cannot precede payment.');
            }Settlement::create(['payment_id' => $p->id, 'state' => 'settled', 'effective_at' => $d, 'reason' => $data['reason']]);
            $p->settlement = 'settled';
            Orders::touch($p);
            Orders::audit($p, 'bank_settled', ['reason' => $data['reason']]);

            return ['id' => $p->id];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string|int, mixed>
     */
    public static function reverse(int $allocation, array $data): array
    {
        return self::once($data['key'], ['op' => 'reverse', 'allocation' => $allocation, ...$data], function () use ($allocation, $data) {
            $a = Allocation::findOrFail($allocation);
            $p = Orders::lock(Payment::class, $a->payment_id);
            $i = Orders::lock(Invoice::class, $a->invoice_id, $data['version']);
            if ($a->amount <= 0 || Allocation::where('reverses_id', $a->id)->exists()) {
                Pricing::fail('This allocation is already reversed.');
            }if (Credit::where('invoice_id', $i->id)->exists()) {
                Pricing::fail('A credited invoice requires a linked correction; payment reversal is blocked to preserve refund integrity.');
            }$d = self::date($data['effective_at']);
            if ($d->lt($a->effective_at)) {
                Pricing::fail('Reversal cannot precede allocation.');
            }self::chronological($i, $d);
            Allocation::create(['payment_id' => $p->id, 'invoice_id' => $i->id, 'amount' => -$a->amount, 'effective_at' => $d, 'reverses_id' => $a->id, 'reason' => $data['reason']]);
            Orders::touch($i);
            Orders::audit($i, 'allocation_reversed', ['allocation' => $a->id, 'reason' => $data['reason']]);

            return ['id' => $i->id];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string|int, mixed>
     */
    public static function credit(int $invoice, array $data): array
    {
        return self::once($data['key'], ['op' => 'credit', 'invoice' => $invoice, ...$data], function () use ($invoice, $data) {
            $i = Orders::lock(Invoice::class, $invoice, $data['version']);
            if ($i->state !== 'posted') {
                Pricing::fail('Credit a posted invoice.');
            }$d = self::date($data['effective_at']);
            if ($d->lt($i->effective_at)) {
                Pricing::fail('Credit date precedes invoice.');
            }self::chronological($i, $d);
            $previous = Credit::where('invoice_id', $i->id)->get();
            $out = [];
            $amount = 0;
            $net = 0;
            $tax = 0;
            foreach ($i->lines as $line) {
                $q = (int) ($data['quantities'][$line['id']] ?? 0);
                if ($q < 0) {
                    Pricing::fail('Credit quantities cannot be negative.');
                }if (! $q) {
                    continue;
                }$used = 0;
                foreach ($previous as $c) {
                    foreach ($c->lines as $cl) {
                        if ($cl['line_id'] === $line['id']) {
                            $used += $cl['quantity'];
                        }
                    }
                }if ($q + $used > $line['quantity']) {
                    Pricing::fail('Cumulative credit quantity exceeds the original garment quantity.');
                }$part = [];
                foreach (['total', 'net', 'tax'] as $k) {
                    $part[$k] = Pricing::round(bcdiv(bcmul(Pricing::decimal($line['totals'][$k]), (string) ($q + $used), 0), Pricing::decimal($line['quantity']), 16)) - Pricing::round(bcdiv(bcmul(Pricing::decimal($line['totals'][$k]), (string) $used, 0), Pricing::decimal($line['quantity']), 16));
                }$part['tax'] = $part['total'] - $part['net'];
                $out[] = ['line_id' => $line['id'], 'description' => $line['description'], 'quantity' => $q, ...$part];
                $amount += $part['total'];
                $net += $part['net'];
                $tax += $part['tax'];
            }
            if (! $out) {
                Pricing::fail('Select at least one garment quantity to credit.');
            }$c = Credit::create(['reference' => Orders::number('CR'), 'invoice_id' => $i->id, 'customer_id' => $i->customer_id, 'lines' => $out, 'amount' => $amount, 'net' => $net, 'tax' => $tax, 'effective_at' => $d, 'reason' => $data['reason']]);
            $share = min($amount, self::balance($i));
            if ($share) {
                CreditAllocation::create(['credit_id' => $c->id, 'invoice_id' => $i->id, 'amount' => $share, 'effective_at' => $d]);
            }Orders::touch($i);
            Orders::audit($i, 'credit_posted', ['credit' => $c->id, 'cash' => 0, 'receivable_settled' => $share, 'refundable' => $amount - $share]);

            return ['id' => $c->id];
        });
    }

    public static function refundable(Credit $c): int
    {
        return $c->amount - (int) CreditAllocation::where('credit_id', $c->id)->sum('amount') - (int) Refund::where('credit_id', $c->id)->sum('amount');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string|int, mixed>
     */
    public static function refund(int $credit, array $data): array
    {
        return self::once($data['key'], ['op' => 'refund', 'credit' => $credit, ...$data], function () use ($credit, $data) {
            $c = Orders::lock(Credit::class, $credit);
            $i = Orders::lock(Invoice::class, $c->invoice_id, $data['version']);
            $amount = Pricing::minor($data['amount']);
            if (! $amount || $amount > self::refundable($c)) {
                Pricing::fail('Refund exceeds the remaining refundable credit.');
            }PaymentMethod::where('active', true)->where('id', $data['payment_method_id'])->firstOrFail();
            $d = self::date($data['effective_at']);
            if ($d->lt($c->effective_at)) {
                Pricing::fail('Refund cannot precede credit.');
            }self::chronological($i, $d);
            $r = Refund::create(['reference' => Orders::number('RF'), 'credit_id' => $c->id, 'payment_method_id' => $data['payment_method_id'], 'amount' => $amount, 'effective_at' => $d, 'reason' => $data['reason']]);
            Orders::touch($i);
            Orders::audit($i, 'refund_recorded', ['refund' => $r->id, 'amount' => $amount]);

            return ['id' => $r->id];
        });
    }
}
