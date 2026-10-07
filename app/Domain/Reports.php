<?php

namespace App\Domain;

use App\Models\Allocation;
use App\Models\BusinessSetting;
use App\Models\Credit;
use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Invoice;
use App\Models\OrderLine;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\StitchingOrder;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class Reports
{
    /** @return Builder<Invoice> */
    public static function invoices(?CarbonInterface $cutoff = null): Builder
    {
        $cutoff = $cutoff ?? now();
        $cash = DB::table('allocations')
            ->select('invoice_id')
            ->selectRaw('SUM(amount) as cash')
            ->where('effective_at', '<=', $cutoff)
            ->groupBy('invoice_id');
        $credits = DB::table('credit_allocations')
            ->select('invoice_id')
            ->selectRaw('SUM(amount) as noncash')
            ->where('effective_at', '<=', $cutoff)
            ->groupBy('invoice_id');

        return Invoice::query()
            ->leftJoinSub($cash, 'cashflow', fn ($j) => $j->on('invoices.id', '=', 'cashflow.invoice_id'))
            ->leftJoinSub($credits, 'creditflow', fn ($j) => $j->on('invoices.id', '=', 'creditflow.invoice_id'))
            ->select('invoices.*')
            ->selectRaw('COALESCE(cashflow.cash,0) as cash, COALESCE(creditflow.noncash,0) as noncash')
            ->where('state', 'posted')
            ->where('invoices.effective_at', '<=', $cutoff);
    }

    /**
     * @return array<string|int, mixed>
     */
    public static function customer(Customer $c): array
    {
        $orders = $c->orders();
        $confirmed = (clone $orders)->whereNotNull('confirmed_at')->where('stage', '!=', 'Cancelled');
        $completed = (clone $orders)->whereNotNull('delivered_at');
        $last = (clone $confirmed)->latest('confirmed_at')->first();
        $done = (clone $completed)->latest('delivered_at')->first();
        $invoices = self::invoices()->where('customer_id', $c->id)->get();
        $credits = Credit::where('customer_id', $c->id)->sum('net');

        return [
            'since' => $c->created_at,
            'confirmed_count' => $confirmed->count(),
            'completed_count' => $n = $completed->count(),
            'classification' => $n === 0 ? 'No completed orders' : ($n === 1 ? 'One-time' : 'Returning'),
            'latest_confirmed' => $last?->reference,
            'latest_completed' => $done?->reference,
            'days_since_confirmed' => $last ? (int) $last->confirmed_at->diffInDays(now()) : null,
            'days_since_completed' => $done ? (int) $done->delivered_at->diffInDays(now()) : null,
            'net_spending' => $invoices->sum(fn ($i) => $i->totals['net']) - (int) $credits,
            'allocated' => $invoices->sum('cash'),
            'due' => $invoices->sum(fn ($i) => $i->totals['total'] - $i->getAttribute('cash') - $i->getAttribute('noncash')),
            'active_orders' => (clone $orders)->whereNotNull('confirmed_at')->whereNotIn('stage', ['Delivered', 'Cancelled'])->get(['id', 'reference', 'stage', 'promised_at']),
        ];
    }

    /**
     * @param  Builder<StitchingOrder>  $q
     * @param  array<string, mixed>  $f
     */
    private static function applyOrderDimensions(Builder $q, array $f, bool $legacyOrderDates): void
    {
        if (! empty($f['q'])) {
            $v = $f['q'];
            $q->where(fn ($q) => $q
                ->where('reference', 'like', "%$v%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$v%")->orWhere('phone', 'like', "%$v%")));
        }
        if (! empty($f['stage'])) {
            $q->where('stage', $f['stage']);
        }
        if (! empty($f['customer_id'])) {
            $q->where('customer_id', $f['customer_id']);
        }
        if (! empty($f['product_id'])) {
            $q->whereHas('lines', fn ($line) => $line->where('product_id', $f['product_id']));
        }
        if (! empty($f['late'])) {
            $q->whereNotNull('confirmed_at')
                ->whereNotIn('stage', ['Delivered', 'Cancelled'])
                ->where('promised_at', '<', now());
        }

        $billing = $f['billing_status'] ?? null;
        $cutoff = ! empty($f['cutoff']) ? Orders::date($f['cutoff']) : null;
        if ($billing === 'unbilled') {
            if ($cutoff) {
                $q->whereDoesntHave('invoice', fn ($invoice) => $invoice->where('created_at', '<=', $cutoff));
            } else {
                $q->whereDoesntHave('invoice');
            }
        } elseif ($billing === 'draft') {
            $q->whereHas('invoice', function ($invoice) use ($cutoff) {
                if (! $cutoff) {
                    $invoice->where('state', 'draft');

                    return;
                }
                $invoice->where('created_at', '<=', $cutoff)
                    ->where(fn ($state) => $state->where('state', 'draft')->orWhere('effective_at', '>', $cutoff));
            });
        } elseif ($billing === 'posted') {
            $q->whereHas('invoice', function ($invoice) use ($cutoff) {
                $invoice->where('state', 'posted');
                if ($cutoff) {
                    $invoice->where('effective_at', '<=', $cutoff);
                }
            });
        }

        self::applyRange(
            $q,
            'ordered_at',
            $f,
            'order_from',
            'order_to',
            $legacyOrderDates ? 'from' : null,
            $legacyOrderDates ? 'to' : null,
        );
        self::applyRange($q, 'promised_at', $f, 'promised_from', 'promised_to');
        self::applyRange($q, 'delivered_at', $f, 'delivered_from', 'delivered_to');
    }

    /**
     * @param  Builder<*>  $q
     * @param  array<string, mixed>  $f
     */
    private static function applyRange(Builder $q, string $column, array $f, string $fromKey, string $toKey, ?string $legacyFrom = null, ?string $legacyTo = null): void
    {
        $from = $f[$fromKey] ?? ($legacyFrom ? ($f[$legacyFrom] ?? null) : null);
        $to = $f[$toKey] ?? ($legacyTo ? ($f[$legacyTo] ?? null) : null);
        if (! empty($from)) {
            $q->where($column, '>=', Orders::date($from));
        }
        if (! empty($to)) {
            $q->where($column, '<=', Orders::date($to));
        }
    }

    /**
     * @param  array<string, mixed>  $f
     * @return Builder<StitchingOrder>
     */
    public static function orderQuery(array $f): Builder
    {
        $q = StitchingOrder::query()->with('customer', 'invoice')->withCount('lines');
        self::applyOrderDimensions($q, $f, true);

        return $q;
    }

    /**
     * @param  array<string, mixed>  $f
     * @return Builder<StitchingOrder>
     */
    private static function reportOrderQuery(array $f): Builder
    {
        $q = StitchingOrder::query();
        self::applyOrderDimensions($q, $f, false);

        return $q;
    }

    /**
     * @param  array<string, mixed>  $f
     */
    private static function hasOrderDimensions(array $f): bool
    {
        foreach (['q', 'stage', 'customer_id', 'product_id', 'late', 'billing_status', 'order_from', 'order_to', 'promised_from', 'promised_to', 'delivered_from', 'delivered_to'] as $key) {
            if (! empty($f[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{cash: int, noncash: int, due: int}  $position
     */
    private static function matchesPaymentStatus(array $position, ?string $status): bool
    {
        if (! $status) {
            return true;
        }
        if ($status === 'credited') {
            return $position['noncash'] > 0;
        }
        if ($status === 'paid') {
            return $position['due'] <= 0;
        }
        if ($status === 'unpaid') {
            return $position['due'] > 0 && $position['cash'] === 0 && $position['noncash'] === 0;
        }
        if ($status === 'partial') {
            return $position['due'] > 0 && ($position['cash'] > 0 || $position['noncash'] > 0);
        }

        return true;
    }

    /** @param array{cash: int, noncash: int, due: int} $position */
    private static function paymentStatusLabel(array $position): string
    {
        if ($position['due'] <= 0) {
            return $position['noncash'] > 0 ? 'Paid / credited' : 'Paid';
        }
        if ($position['cash'] > 0 || $position['noncash'] > 0) {
            return $position['noncash'] > 0 ? 'Partial / credited' : 'Partial';
        }

        return 'Unpaid';
    }

    /**
     * @return array{cash: int, noncash: int, due: int}
     */
    private static function selectedInvoicePosition(Invoice $invoice): array
    {
        $cash = (int) $invoice->getAttribute('cash');
        $noncash = (int) $invoice->getAttribute('noncash');

        return [
            'cash' => $cash,
            'noncash' => $noncash,
            'due' => (int) $invoice->totals['total'] - $cash - $noncash,
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array<int, array<string|int, mixed>>
     */
    private static function customerReportRows(array $f, CarbonInterface $cutoff): array
    {
        $customers = Customer::query()
            ->when(! empty($f['customer_id']), fn ($q) => $q->where('id', $f['customer_id']))
            ->when(! empty($f['q']), function ($q) use ($f) {
                $value = '%'.$f['q'].'%';
                $q->where(fn ($customer) => $customer->where('name', 'like', $value)->orWhere('phone', 'like', $value));
            });

        $orderFilters = $f;
        unset($orderFilters['q']);
        if (self::hasOrderDimensions($orderFilters)) {
            $customers->whereIn('id', self::reportOrderQuery($orderFilters)->select('customer_id'));
        }
        if (! empty($f['invoice_from']) || ! empty($f['invoice_to']) || ! empty($f['payment_status'])) {
            $invoiceFilters = $f;
            unset($invoiceFilters['q']);
            $invoiceCustomers = self::filteredInvoices($invoiceFilters, $cutoff, false)->pluck('customer_id')->unique()->all();
            $customers->whereIn('id', $invoiceCustomers);
        }
        if (! empty($f['payment_from']) || ! empty($f['payment_to'])) {
            $payments = Payment::query()->select('customer_id');
            self::applyRange($payments, 'effective_at', $f, 'payment_from', 'payment_to');
            $customers->whereIn('id', $payments);
        }

        $rows = $customers->orderBy('name')->get();
        if ($rows->isEmpty()) {
            return [];
        }
        $customerIds = $rows->pluck('id')->all();
        $ordersByCustomer = StitchingOrder::query()
            ->whereIn('customer_id', $customerIds)
            ->get(['id', 'customer_id', 'reference', 'stage', 'confirmed_at', 'delivered_at'])
            ->groupBy('customer_id');
        $invoicesByCustomer = self::invoices($cutoff)
            ->whereIn('customer_id', $customerIds)
            ->get()
            ->groupBy('customer_id');
        $creditsByCustomer = Credit::query()
            ->whereIn('customer_id', $customerIds)
            ->where('effective_at', '<=', $cutoff)
            ->select('customer_id')
            ->selectRaw('SUM(net) as net')
            ->groupBy('customer_id')
            ->pluck('net', 'customer_id');

        return $rows->map(function (Customer $customer) use ($ordersByCustomer, $invoicesByCustomer, $creditsByCustomer) {
            $orders = $ordersByCustomer->get($customer->id, collect());
            $confirmed = $orders->filter(fn ($order) => $order->confirmed_at && $order->stage !== 'Cancelled');
            $completed = $orders->filter(fn ($order) => $order->delivered_at !== null);
            $last = $confirmed->sortByDesc('confirmed_at')->first();
            $done = $completed->sortByDesc('delivered_at')->first();
            $invoices = $invoicesByCustomer->get($customer->id, collect());
            $completedCount = $completed->count();

            return [
                'name' => $customer->name,
                'phone' => $customer->phone,
                'since' => $customer->created_at,
                'confirmed_count' => $confirmed->count(),
                'completed_count' => $completedCount,
                'classification' => $completedCount === 0 ? 'No completed orders' : ($completedCount === 1 ? 'One-time' : 'Returning'),
                'latest_confirmed' => $last?->reference,
                'latest_completed' => $done?->reference,
                'days_since_confirmed' => $last ? (int) $last->confirmed_at->diffInDays(now()) : null,
                'days_since_completed' => $done ? (int) $done->delivered_at->diffInDays(now()) : null,
                'net_spending' => $invoices->sum(fn ($invoice) => $invoice->totals['net']) - (int) ($creditsByCustomer->get($customer->id) ?? 0),
                'allocated' => $invoices->sum(fn ($invoice) => (int) $invoice->getAttribute('cash')),
                'due' => $invoices->sum(fn ($invoice) => (int) $invoice->totals['total'] - (int) $invoice->getAttribute('cash') - (int) $invoice->getAttribute('noncash')),
            ];
        })->all();
    }

    /**
     * @return array<string|int, mixed>
     */
    public static function dashboard(string $period): array
    {
        $s = BusinessSetting::findOrFail(1);
        $local = now()->timezone($s->timezone);
        $from = match ($period) {
            'week' => $local->copy()->startOfWeek(),
            'month' => $local->copy()->startOfMonth(),
            default => $local->copy()->startOfDay(),
        };
        $from->utc();
        $posted = self::invoices()->get();
        $window = $posted->filter(fn ($i) => $i->effective_at->gte($from));
        $completed = Customer::withCount(['orders as completed_count' => fn ($q) => $q->whereNotNull('delivered_at')])->get();
        $population = $completed->where('completed_count', '>', 0)->count();
        $returning = $completed->where('completed_count', '>', 1)->count();
        $dayAfter = $local->copy()->addDays(2)->startOfDay()->utc();
        $today = $local->copy()->startOfDay()->utc();
        $days = [];
        for ($n = 6; $n >= 0; $n--) {
            $start = $local->copy()->subDays($n)->startOfDay()->utc();
            $end = $start->copy()->timezone($s->timezone)->addDay()->utc();
            $days[] = [
                'date' => $start->copy()->timezone($s->timezone)->toDateString(),
                'net' => $posted->filter(fn ($i) => $i->effective_at->gte($start) && $i->effective_at->lt($end))->sum(fn ($i) => $i->totals['net']) - (int) Credit::where('effective_at', '>=', $start)->where('effective_at', '<', $end)->sum('net'),
            ];
        }

        return [
            'period' => $period,
            'period_from' => $from->toIso8601String(),
            'refreshed_at' => now()->toIso8601String(),
            'net_revenue' => $window->sum(fn ($i) => $i->totals['net']) - (int) Credit::whereBetween('effective_at', [$from, now()])->sum('net'),
            'discounts' => $window->sum(fn ($i) => $i->totals['discount']),
            'allocated_receipts' => (int) Allocation::whereBetween('effective_at', [$from, now()])->sum('amount'),
            'refunds' => (int) Refund::whereBetween('effective_at', [$from, now()])->sum('amount'),
            'outstanding_now' => $posted->sum(fn ($i) => $i->totals['total'] - $i->getAttribute('cash') - $i->getAttribute('noncash')),
            'unbilled' => StitchingOrder::whereNotNull('confirmed_at')->where('stage', '!=', 'Cancelled')->whereDoesntHave('invoice', fn ($q) => $q->where('state', 'posted'))->get()->sum(fn ($o) => $o->totals['total']),
            'late' => self::orderQuery(['late' => true])->count(),
            'created_today' => StitchingOrder::where('ordered_at', '>=', $today)->count(),
            'delivered_today' => StitchingOrder::where('delivered_at', '>=', $today)->count(),
            'stages' => StitchingOrder::whereNotNull('confirmed_at')->select('stage')->selectRaw('COUNT(*) as count')->groupBy('stage')->get(),
            'returning' => $returning,
            'population' => $population,
            'returning_rate' => $population ? round($returning / $population * 100, 2) : 0,
            'trend' => $days,
            'deliveries' => self::orderQuery([])->whereNotNull('confirmed_at')->whereNotIn('stage', ['Delivered', 'Cancelled'])->where('promised_at', '<', $dayAfter)->orderBy('promised_at')->limit(30)->get(),
            'follow_ups' => FollowUp::whereNull('completed_at')->where('due_at', '<=', now())->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array<string|int, mixed>
     */
    public static function financialEvents(array $f): array
    {
        $cutoff = ! empty($f['cutoff']) ? Orders::date($f['cutoff']) : now();
        $invoices = self::filteredInvoices($f, $cutoff, false);
        if ($invoices->isEmpty()) {
            return [];
        }
        $invoiceIds = $invoices->keys()->all();
        $creditInvoiceIds = Credit::whereIn('invoice_id', $invoiceIds)->pluck('invoice_id', 'id');
        $rows = [];
        foreach ([Invoice::query(), Credit::query(), Allocation::query(), Refund::query()] as $q) {
            $model = $q->getModel();
            $q->where('effective_at', '<=', $cutoff);
            if ($model instanceof Invoice) {
                $q->whereIn('id', $invoiceIds)->where('state', 'posted');
            } elseif ($model instanceof Refund) {
                $q->whereIn('credit_id', Credit::select('id')->whereIn('invoice_id', $invoiceIds));
            } else {
                $q->whereIn('invoice_id', $invoiceIds);
            }
            if (! empty($f['from'])) {
                $q->where('effective_at', '>=', Orders::date($f['from']));
            }
            if (! empty($f['to'])) {
                $q->where('effective_at', '<=', Orders::date($f['to']));
            }
            if ($model instanceof Allocation && (! empty($f['payment_from']) || ! empty($f['payment_to']))) {
                $payments = Payment::select('id');
                self::applyRange($payments, 'effective_at', $f, 'payment_from', 'payment_to');
                $q->whereIn('payment_id', $payments);
            }
            if ($model instanceof Refund) {
                self::applyRange($q, 'effective_at', $f, 'payment_from', 'payment_to');
            }
            foreach ($q->get() as $event) {
                $row = self::financialRow($event, $invoices, $creditInvoiceIds);
                if ($row !== null) {
                    $rows[] = $row;
                }
            }
        }
        usort($rows, fn ($a, $b) => strcmp($a['effective_at'], $b['effective_at']));

        return $rows;
    }

    /**
     * @param  Collection<int|string, Invoice>  $invoices
     * @param  Collection<int|string, int>  $creditInvoiceIds
     * @return array<string, mixed>|null
     */
    private static function financialRow(Invoice|Credit|Allocation|Refund $event, Collection $invoices, Collection $creditInvoiceIds): ?array
    {
        if ($event instanceof Invoice) {
            $invoice = $invoices->get($event->id);
            $kind = 'invoice';
            $net = $event->totals['net'];
            $discount = $event->totals['discount'];
            $cash = 0;
        } else {
            $invoiceId = $event instanceof Refund ? $creditInvoiceIds->get($event->credit_id) : $event->invoice_id;
            $invoice = $invoices->get((int) $invoiceId);
            $kind = $event instanceof Credit ? 'credit' : ($event instanceof Refund ? 'refund' : 'payment');
            $net = $event instanceof Credit ? -$event->net : 0;
            $discount = 0;
            $cash = $event instanceof Credit ? 0 : ($event instanceof Refund ? -$event->amount : $event->amount);
        }
        if (! $invoice) {
            return null;
        }

        return [
            'kind' => $kind,
            'reference' => $event instanceof Allocation ? 'Allocation '.$event->id : $event->reference,
            'invoice' => $invoice->reference,
            'customer' => $invoice->contact['name'],
            'effective_at' => $event->effective_at->toIso8601String(),
            'net_minor' => $net,
            'discount_minor' => $discount,
            'cash_minor' => $cash,
        ];
    }

    /**
     * @param  Builder<Invoice>  $invoices
     * @param  array<string, mixed>  $f
     */
    private static function applyInvoiceFilters(Builder $invoices, array $f, bool $legacyInvoiceDates): void
    {
        if (! empty($f['customer_id'])) {
            $invoices->where('customer_id', $f['customer_id']);
        }
        self::applyRange(
            $invoices,
            'invoices.effective_at',
            $f,
            'invoice_from',
            'invoice_to',
            $legacyInvoiceDates ? 'from' : null,
            $legacyInvoiceDates ? 'to' : null,
        );
        if (self::hasOrderDimensions($f)) {
            $invoices->whereIn('order_id', self::reportOrderQuery($f)->select('orders.id'));
        }
    }

    /**
     * @param  array<string, mixed>  $f
     * @return Collection<int|string, Invoice>
     */
    private static function filteredInvoices(array $f, CarbonInterface $cutoff, bool $legacyInvoiceDates): Collection
    {
        $query = self::invoices($cutoff);
        self::applyInvoiceFilters($query, $f, $legacyInvoiceDates);

        return $query->get()
            ->filter(fn (Invoice $invoice) => self::matchesPaymentStatus(self::selectedInvoicePosition($invoice), $f['payment_status'] ?? null))
            ->keyBy('id');
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array<int, array<string|int, mixed>>
     */
    public static function rows(string $type, array $f): array
    {
        $cutoff = ! empty($f['cutoff']) ? Orders::date($f['cutoff']) : now();
        if (in_array($type, ['revenue', 'cashflow', 'daily', 'weekly', 'monthly'])) {
            $events = self::financialEvents($f);
            if ($type === 'revenue') {
                return array_values(array_filter($events, fn ($e) => in_array($e['kind'], ['invoice', 'credit'])));
            }
            if ($type === 'cashflow') {
                return array_values(array_filter($events, fn ($e) => in_array($e['kind'], ['payment', 'refund'])));
            }
            $group = [];
            $tz = BusinessSetting::findOrFail(1)->timezone;
            foreach ($events as $e) {
                $date = Carbon::parse($e['effective_at'])->timezone($tz);
                $key = match ($type) {
                    'weekly' => $date->startOfWeek()->toDateString(),
                    'monthly' => $date->format('Y-m'),
                    default => $date->toDateString(),
                };
                $group[$key] ??= ['period' => $key, 'net_minor' => 0, 'discount_minor' => 0, 'net_cash_minor' => 0];
                $group[$key]['net_minor'] += $e['net_minor'];
                $group[$key]['discount_minor'] += $e['discount_minor'];
                $group[$key]['net_cash_minor'] += $e['cash_minor'];
            }
            ksort($group);

            return array_values($group);
        }

        if (in_array($type, ['outstanding', 'invoices', 'profit'])) {
            $invoices = self::filteredInvoices($f, $cutoff, true);
            $invoiceIds = $invoices->keys()->all();
            $creditTotals = Credit::query()
                ->select('invoice_id')
                ->selectRaw('SUM(net) as net, SUM(tax) as tax')
                ->whereIn('invoice_id', $invoiceIds)
                ->where('effective_at', '<=', $cutoff)
                ->groupBy('invoice_id')
                ->get()
                ->keyBy('invoice_id');
            $linesByOrder = $type === 'profit'
                ? OrderLine::whereIn('order_id', $invoices->pluck('order_id')->all())->get()->groupBy('order_id')
                : collect();
            $rows = [];
            foreach ($invoices as $i) {
                $position = self::selectedInvoicePosition($i);
                if ($type === 'outstanding' && $position['due'] === 0) {
                    continue;
                }
                $credit = $creditTotals->get($i->id);
                $credits = (int) ($credit?->getAttribute('net') ?? 0);
                $creditTax = (int) ($credit?->getAttribute('tax') ?? 0);
                $row = [
                    'invoice' => $i->reference,
                    'customer' => $i->contact['name'],
                    'date' => $i->effective_at->toIso8601String(),
                    'payment_status' => self::paymentStatusLabel($position),
                    'net_minor' => $i->totals['net'] - $credits,
                    'tax_minor' => $i->totals['tax'] - $creditTax,
                    'cash_allocated_minor' => $position['cash'],
                    'credit_settlement_minor' => $position['noncash'],
                    'due_minor' => $position['due'],
                ];
                if ($type === 'profit') {
                    $lines = $linesByOrder->get($i->order_id, collect());
                    $known = $lines->where('cost_known', true);
                    $cost = $known->sum(fn ($line) => $line->cost * $line->quantity);
                    $covered = $known->sum('quantity');
                    $quantity = $lines->sum('quantity');
                    $row['direct_cost_minor'] = $cost;
                    $row['cost_coverage'] = $covered.'/'.$quantity;
                    $row['stitching_margin_minor'] = $covered === $quantity ? $row['net_minor'] - $cost : 'N/A';
                }
                $rows[] = $row;
            }

            return $rows;
        }

        if ($type === 'payments') {
            $invoices = self::filteredInvoices($f, $cutoff, false);
            if ($invoices->isEmpty()) {
                return [];
            }
            $q = Allocation::query()->where('effective_at', '<=', $cutoff)->whereIn('invoice_id', $invoices->keys()->all());
            self::applyRange($q, 'effective_at', $f, 'from', 'to');
            if (! empty($f['payment_from']) || ! empty($f['payment_to'])) {
                $paymentIds = Payment::select('id');
                self::applyRange($paymentIds, 'effective_at', $f, 'payment_from', 'payment_to');
                $q->whereIn('payment_id', $paymentIds);
            }
            $allocations = $q->get();
            $payments = Payment::whereIn('id', $allocations->pluck('payment_id')->unique())->get()->keyBy('id');
            $rows = [];
            foreach ($allocations as $allocation) {
                $invoice = $invoices->get($allocation->invoice_id);
                $payment = $payments->get($allocation->payment_id);
                if (! $invoice || ! $payment) {
                    continue;
                }
                $rows[] = [
                    'allocation' => $allocation->id,
                    'payment' => $payment->reference,
                    'invoice' => $invoice->reference,
                    'amount_minor' => $allocation->amount,
                    'payment_at' => $payment->effective_at->toIso8601String(),
                    'allocated_at' => $allocation->effective_at->toIso8601String(),
                    'reverses' => $allocation->reverses_id,
                ];
            }

            return $rows;
        }

        if ($type === 'customers') {
            return self::customerReportRows($f, $cutoff);
        }

        if ($type === 'credits') {
            $invoices = self::filteredInvoices($f, $cutoff, false);
            if ($invoices->isEmpty()) {
                return [];
            }
            $query = Credit::where('effective_at', '<=', $cutoff)->whereIn('invoice_id', $invoices->keys()->all());
            self::applyRange($query, 'effective_at', $f, 'from', 'to');
            $credits = $query->get();
            $refundTotals = Refund::query()
                ->select('credit_id')
                ->selectRaw('SUM(amount) as amount')
                ->whereIn('credit_id', $credits->pluck('id')->all())
                ->where('effective_at', '<=', $cutoff)
                ->groupBy('credit_id')
                ->pluck('amount', 'credit_id');
            $rows = [];
            foreach ($credits as $credit) {
                $invoice = $invoices->get($credit->invoice_id);
                if (! $invoice) {
                    continue;
                }
                $rows[] = [
                    'credit' => $credit->reference,
                    'invoice' => $invoice->reference,
                    'amount_minor' => $credit->amount,
                    'net_minor' => $credit->net,
                    'refunded_minor' => (int) ($refundTotals->get($credit->id) ?? 0),
                    'effective_at' => $credit->effective_at->toIso8601String(),
                ];
            }

            return $rows;
        }

        $orders = self::orderQuery($f)->with('lines')->get();
        $postedInvoices = self::invoices($cutoff)->whereIn('order_id', $orders->pluck('id'))->get()->keyBy('order_id');
        $rows = [];
        foreach ($orders as $order) {
            $invoice = $order->invoice;
            $postedInvoice = $postedInvoices->get($order->id);
            $billingState = $postedInvoice ? 'posted' : ($invoice && $invoice->created_at->lte($cutoff) ? 'draft' : 'unbilled');
            $position = $postedInvoice ? self::selectedInvoicePosition($postedInvoice) : null;
            if (! empty($f['payment_status']) && (! $position || ! self::matchesPaymentStatus($position, $f['payment_status']))) {
                continue;
            }
            $base = [
                'order' => $order->reference,
                'customer' => $order->customer->name,
                'stage' => $order->stage,
                'billing_status' => $billingState,
                'payment_status' => $position ? self::paymentStatusLabel($position) : 'N/A',
                'confirmed' => $order->confirmed_at ? 'Yes' : 'No',
                'ordered_at' => $order->ordered_at->toIso8601String(),
                'promised_at' => $order->promised_at->toIso8601String(),
            ];
            if ($type === 'items') {
                foreach ($order->lines as $line) {
                    $rows[] = [
                        ...$base,
                        'garment' => $line->description,
                        'quantity' => $line->quantity,
                        'net_minor' => $line->totals['net'],
                        'total_minor' => $line->totals['total'],
                    ];
                }
            } elseif ($type === 'delivery') {
                $rows[] = [
                    ...$base,
                    'original_promised_at' => $order->original_promised_at?->toIso8601String(),
                    'delivered_at' => $order->delivered_at?->toIso8601String(),
                    'late_original' => $order->delivered_at && $order->original_promised_at ? ($order->delivered_at->gt($order->original_promised_at) ? 'Yes' : 'No') : 'N/A',
                    'late_latest' => $order->delivered_at ? ($order->delivered_at->gt($order->promised_at) ? 'Yes' : 'No') : 'N/A',
                ];
            } else {
                $rows[] = [
                    ...$base,
                    'quantity' => $order->lines->sum('quantity'),
                    'discount_minor' => $order->totals['discount'],
                    'net_minor' => $order->totals['net'],
                    'total_minor' => $order->totals['total'],
                ];
            }
        }

        return $rows;
    }
}
