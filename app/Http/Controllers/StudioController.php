<?php

namespace App\Http\Controllers;

use App\Domain\Billing;
use App\Domain\Documents;
use App\Domain\Orders;
use App\Domain\Pricing;
use App\Domain\Reports;
use App\Jobs\GenerateReport;
use App\Models\Allocation;
use App\Models\AuditEvent;
use App\Models\BusinessSetting;
use App\Models\Credit;
use App\Models\Customer;
use App\Models\CustomerPrice;
use App\Models\FollowUp;
use App\Models\Invoice;
use App\Models\MeasurementSnapshot;
use App\Models\MeasurementTemplate;
use App\Models\OrderLine;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Refund;
use App\Models\ReportExport;
use App\Models\StitchingOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudioController extends Controller
{
    /**
     * @return array<string|int, mixed>
     */
    private function base(): array
    {
        return ['settings' => BusinessSetting::findOrFail(1), 'products' => Product::orderBy('name')->get(), 'templates' => MeasurementTemplate::orderBy('name')->get(), 'customers' => Customer::where('active', true)->orderBy('name')->get(['id', 'name', 'phone']), 'methods' => PaymentMethod::all()];
    }

    public function index(Request $r, string $section = 'dashboard'): Response
    {
        abort_unless(in_array($section, ['dashboard', 'customers', 'measurement-search', 'orders', 'billing', 'products', 'templates', 'configuration', 'follow-ups', 'reports', 'audit', 'prices', 'exports']), 404);
        $data = $this->base();
        $f = $r->only(['q', 'stage', 'late', 'customer_id', 'product_id', 'billing_status', 'payment_status', 'from', 'to', 'cutoff', 'order_from', 'order_to', 'invoice_from', 'invoice_to', 'payment_from', 'payment_to', 'promised_from', 'promised_to', 'delivered_from', 'delivered_to', 'type', 'period', 'inactive', 'archived']);
        $data['section'] = $section;
        if ($section === 'exports') {
            $data['export_jobs'] = ReportExport::where('user_id', $r->user()->id)->latest()->limit(100)->get(['id', 'report', 'format', 'state', 'error', 'row_count', 'created_at']);
        }
        $data['filters'] = $f;
        if ($section === 'dashboard') {
            $data['dashboard'] = Reports::dashboard(in_array($r->period, ['week', 'month']) ? $r->period : 'today');
        }
        if ($section === 'orders') {
            $data['rows'] = Reports::orderQuery($f)->latest('id')->paginate(25)->withQueryString();
        }
        if ($section === 'customers') {
            $q = Customer::query()->withCount(['orders as completed_count' => fn ($q) => $q->whereNotNull('delivered_at')]);
            if ($r->filled('q')) {
                $v = '%'.$r->q.'%';
                $q->where(fn ($q) => $q->where('name', 'like', $v)->orWhere('phone', 'like', $v)->orWhere('normalized_phone', 'like', $v)->orWhere('whatsapp', 'like', $v)->orWhere('normalized_whatsapp', 'like', $v));
            }if (in_array((int) $r->inactive, [30, 60, 90])) {
                $q->whereHas('orders', fn ($q) => $q->whereNotNull('delivered_at'))->whereDoesntHave('orders', fn ($q) => $q->where('delivered_at', '>=', now()->subDays((int) $r->inactive)));
            }if (! $r->boolean('archived')) {
                $q->where('active', true);
            }$data['rows'] = $q->orderBy('name')->paginate(25)->withQueryString();
        }
        if ($section === 'measurement-search') {
            $q = Customer::query()->where('active', true);
            if ($r->filled('q')) {
                $v = '%'.trim((string) $r->q).'%';
                $q->where(function ($q) use ($v) {
                    $q->where('name', 'like', $v)
                        ->orWhere('phone', 'like', $v)
                        ->orWhere('normalized_phone', 'like', $v)
                        ->orWhere('whatsapp', 'like', $v)
                        ->orWhere('normalized_whatsapp', 'like', $v)
                        ->orWhereHas('orders', fn ($orders) => $orders->where('reference', 'like', $v));
                });
            } else {
                $q->whereHas('orders', function ($orders) {
                    $orders->whereNotNull('confirmed_at')->where('stage', '!=', 'Cancelled')
                        ->whereHas('lines.snapshots', fn ($snapshots) => $snapshots->where('current', true)->whereNotNull('frozen_at'));
                });
            }

            $page = $q->orderBy('name')->paginate(20)->withQueryString();
            $customerIds = collect($page->items())->pluck('id')->all();
            $profiles = [];

            if ($customerIds) {
                $latestLines = OrderLine::query()
                    ->select('order_lines.id', 'order_lines.order_id', 'order_lines.template_id', 'order_lines.description', 'orders.customer_id', 'orders.reference as source_order', 'orders.confirmed_at as source_date')
                    ->join('orders', 'orders.id', '=', 'order_lines.order_id')
                    ->whereIn('orders.customer_id', $customerIds)
                    ->whereNotNull('orders.confirmed_at')
                    ->where('orders.stage', '!=', 'Cancelled')
                    ->orderByDesc('orders.confirmed_at')
                    ->orderByDesc('order_lines.id')
                    ->get()
                    ->groupBy(fn ($line) => $line->getAttribute('customer_id').'|'.$line->template_id)
                    ->map(fn ($group) => $group->first());

                $lineIds = $latestLines->pluck('id')->all();
                $snapshots = MeasurementSnapshot::whereIn('order_line_id', $lineIds)
                    ->where('current', true)
                    ->whereNotNull('frozen_at')
                    ->orderBy('piece')
                    ->get()
                    ->groupBy('order_line_id');

                foreach ($latestLines as $line) {
                    $pieces = $snapshots->get($line->id);
                    if (! $pieces || $pieces->isEmpty()) {
                        continue;
                    }
                    $profiles[$line->getAttribute('customer_id')][] = [
                        'template_id' => $line->template_id,
                        'template_name' => $pieces->first()->template_name,
                        'garment' => $line->description,
                        'source_order_id' => (int) $line->order_id,
                        'source_order' => $line->getAttribute('source_order'),
                        'source_date' => $line->getAttribute('source_date'),
                        'pieces' => $pieces->map(fn ($snapshot) => [
                            'id' => $snapshot->id,
                            'piece' => $snapshot->piece,
                            'revision' => $snapshot->revision,
                            'unit' => $snapshot->unit,
                            'fields' => collect($snapshot->fields)->filter(fn ($field) => $field['is_set'] ?? false)->values()->all(),
                        ])->values()->all(),
                    ];
                }
            }

            $data['measurement_rows'] = $page->through(fn ($customer) => [
                ...$customer->only(['id', 'name', 'phone', 'whatsapp']),
                'measurements' => $profiles[$customer->id] ?? [],
            ]);
        }
        if ($section === 'billing') {
            $q = Invoice::query();
            if ($r->filled('customer_id')) {
                $q->where('customer_id', $r->customer_id);
            }$data['rows'] = $q->latest('id')->paginate(25)->through(fn ($i) => [...$i->toArray(), 'due' => Billing::balance($i)])->withQueryString();
            $data['payments'] = Payment::latest('id')->limit(100)->get()->map(fn ($p) => [...$p->toArray(), 'available' => $p->amount - (int) Allocation::where('payment_id', $p->id)->sum('amount')]);
        }
        if ($section === 'follow-ups') {
            $data['rows'] = FollowUp::query()->leftJoin('customers', 'customers.id', '=', 'follow_ups.customer_id')->select('follow_ups.*', 'customers.name')->orderByRaw('completed_at IS NOT NULL')->orderBy('due_at')->paginate(25)->withQueryString();
        }
        if ($section === 'prices') {
            $data['prices'] = CustomerPrice::all();
        }
        if ($section === 'audit') {
            $data['rows'] = AuditEvent::latest('id')->paginate(50)->withQueryString();
        }
        if ($section === 'reports') {
            $f = $this->reportFilters($r);
            $data['filters'] = $f;
            $type = $r->input('type', 'orders');
            $this->reportType($type);
            $rows = Reports::rows($type, $f);
            $data['report_rows'] = array_slice($rows, 0, 200);
            $data['report_count'] = count($rows);
        }

        return Inertia::render('studio/index', $data);
    }

    public function customer(Customer $customer): Response
    {
        return Inertia::render('studio/customer', [...$this->base(), 'customer' => $customer, 'stats' => Reports::customer($customer), 'orders' => $customer->orders()->latest('id')->paginate(20), 'invoices' => Invoice::where('customer_id', $customer->id)->latest('id')->get()->map(fn ($i) => [...$i->toArray(), 'due' => Billing::balance($i)]), 'payments' => Payment::where('customer_id', $customer->id)->get(), 'followups' => FollowUp::where('customer_id', $customer->id)->orderBy('due_at')->get()]);
    }

    private function phone(?string $value): ?string
    {
        if (! $value) {
            return null;
        }try {
            $util = PhoneNumberUtil::getInstance();
            $n = $util->parse($value, BusinessSetting::findOrFail(1)->country);
            if (! $util->isPossibleNumber($n)) {
                Pricing::fail('Phone number has an invalid length.');
            }

            return $util->format($n, PhoneNumberFormat::E164);
        } catch (NumberParseException) {
            Pricing::fail('Enter a valid phone number, including country code if overseas.');
        }
    }

    public function saveCustomer(Request $r, ?int $id = null): RedirectResponse|JsonResponse
    {
        $d = $r->validate(['name' => 'required|string|max:180', 'phone' => 'required|string|max:40', 'whatsapp' => 'nullable|string|max:40', 'address' => 'nullable|string|max:2000', 'notes' => 'nullable|string|max:4000', 'active' => 'boolean', 'version' => $id ? 'required|integer|min:1' : 'nullable']);
        $d['normalized_phone'] = $this->phone($d['phone']);
        $d['normalized_whatsapp'] = $this->phone($d['whatsapp'] ?? null);
        $c = DB::transaction(function () use ($d, $id) {
            $m = $id ? Orders::lock(Customer::class, $id, $d['version']) : new Customer;
            unset($d['version']);
            $m->fill($d);
            if ($id) {
                Orders::touch($m);
            } else {
                $m->save();
            }Orders::audit($m, $id ? 'customer_updated' : 'customer_created');

            return $m;
        });
        if ($r->expectsJson()) {
            return response()->json($c->only(['id', 'name', 'phone']), 201);
        }
        $duplicates = Customer::where('normalized_phone', $c->normalized_phone)->where('id', '!=', $c->id)->count();

        return redirect('/studio/customers/'.$c->id)->with('success', $duplicates ? 'Customer saved. Another customer shares this phone number; review before creating an order.' : 'Customer saved.');
    }

    public function saveConfig(Request $r, string $kind, ?int $id = null): RedirectResponse
    {
        $rules = match ($kind) {
            'products' => ['name' => 'required|string|max:180', 'category' => 'nullable|string|max:100', 'template_id' => 'required|exists:templates,id', 'price' => 'required', 'cost' => 'nullable', 'cost_known' => 'boolean', 'active' => 'boolean', 'taxes' => 'present|array|max:20', 'taxes.*.name' => 'required|string|max:100', 'taxes.*.type' => 'required|in:percent,fixed', 'taxes.*.rate' => 'required|integer|min:0|max:10000000', 'taxes.*.included' => 'boolean', 'taxes.*.compound' => 'boolean'],
            'templates' => ['name' => 'required|string|max:180', 'code' => 'required|regex:/^[a-z][a-z0-9_]*$/|max:80', 'unit' => 'required|in:inches,centimeters', 'active' => 'boolean', 'notes' => 'nullable|string|max:2000', 'fields' => 'present|array|max:150', 'fields.*.code' => 'required|regex:/^[a-z][a-z0-9_]*$/|distinct', 'fields.*.label' => 'required|string|max:100', 'fields.*.section' => 'required|string|max:100', 'fields.*.type' => 'required|in:numeric,text,choice', 'fields.*.unit' => 'required|in:inches,centimeters,none', 'fields.*.required' => 'boolean', 'fields.*.min' => 'nullable|numeric', 'fields.*.max' => 'nullable|numeric', 'fields.*.sequence' => 'required|integer|min:0', 'fields.*.help' => 'nullable|string|max:1000', 'fields.*.checkbox' => 'boolean', 'fields.*.options' => 'present|array|max:50', 'fields.*.options.*.code' => 'required|string|max:80', 'fields.*.options.*.label' => 'required|string|max:100'],
            'prices' => ['customer_id' => 'required|exists:customers,id', 'product_id' => 'required|exists:products,id', 'price' => 'required', 'active' => 'boolean'],
            'methods' => ['name' => 'required|string|max:100', 'kind' => 'required|in:cash,bank', 'active' => 'boolean'],
            'business' => ['name' => 'required|string|max:180', 'currency' => 'required|regex:/^[A-Z]{3}$/', 'timezone' => 'required|timezone', 'country' => 'required|regex:/^[A-Z]{2}$/', 'phone' => 'nullable|string|max:40', 'address' => 'nullable|string|max:2000', 'invoice_footer' => 'nullable|string|max:2000', 'credit_delivery' => 'boolean', 'cost_reporting' => 'boolean', 'recent_days' => 'required|integer|min:1|max:3650', 'refresh_seconds' => 'required|integer|min:15|max:3600', 'whatsapp_template' => 'required|string|max:2000'],
            default => abort(404)
        };
        $d = $r->validate([...$rules, 'version' => $id ? 'required|integer|min:1' : 'nullable']);
        $class = match ($kind) {
            'products' => Product::class,'templates' => MeasurementTemplate::class,'prices' => CustomerPrice::class,'methods' => PaymentMethod::class,'business' => BusinessSetting::class
        };
        DB::transaction(function () use ($d, $id, $class, $kind) {
            $m = $id ? Orders::lock($class, $id, $d['version']) : new $class;
            unset($d['version']);
            foreach (['price', 'cost'] as $money) {
                if (array_key_exists($money, $d)) {
                    $d[$money] = Pricing::minor($d[$money] ?? '0');
                }
            }
            if ($kind === 'business' && Invoice::exists() && $m->getAttribute('currency') !== $d['currency']) {
                Pricing::fail('Currency cannot change after invoicing.');
            }
            if ($kind === 'templates') {
                if (MeasurementTemplate::where('code', $d['code'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
                    Pricing::fail('Template code is already used.');
                }foreach ($d['fields'] as $f) {
                    if (isset($f['min'],$f['max']) && bccomp(Pricing::decimal($f['min']), Pricing::decimal($f['max']), 4) > 0) {
                        Pricing::fail('Field minimum exceeds maximum.');
                    }$codes = array_column($f['options'], 'code');
                    if (count($codes) !== count(array_unique($codes))) {
                        Pricing::fail('Choice codes must be unique.');
                    }
                }if ($id) {
                    $d['revision'] = $m->getAttribute('revision') + 1;
                }
            }
            if ($kind === 'prices' && CustomerPrice::where('customer_id', $d['customer_id'])->where('product_id', $d['product_id'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
                Pricing::fail('Edit the existing customer/product price instead of creating a duplicate.');
            }
            $m->fill($d);
            if ($id) {
                Orders::touch($m);
            } else {
                $m->save();
            }Orders::audit($m, 'configuration_saved');
        });

        return back()->with('success', 'Configuration saved. Existing orders and measurements are unchanged.');
    }

    public function logo(Request $r): RedirectResponse
    {
        $d = $r->validate(['logo' => 'required|file|mimes:png,jpg,jpeg|max:2048', 'version' => 'required|integer']);
        DB::transaction(function () use ($r, $d) {
            $s = Orders::lock(BusinessSetting::class, 1, $d['version']);
            $path = $r->file('logo')->store('logos', 'local');
            if ($path === false) {
                throw new \RuntimeException('Logo could not be saved.');
            }
            $s->logo = $path;
            Orders::touch($s);
            Orders::audit($s, 'logo_updated');
        });

        return back()->with('success', 'Logo saved.');
    }

    public function order(StitchingOrder $order): Response
    {
        $order->load('lines.snapshots', 'customer', 'invoice');
        $invoice = $order->invoice;
        $previous = [];
        foreach ($order->lines as $line) {
            $previous[$line->template_id] = Orders::previous($order->customer_id, $line->template_id, $order->id)->map(function ($s) {
                $o = OrderLine::findOrFail($s->order_line_id)->order;

                return [...$s->toArray(), 'source_order' => $o->reference, 'source_date' => $o->confirmed_at];
            });
        }

        return Inertia::render('studio/order', [...$this->base(), 'order' => $order, 'available_payments' => Payment::where('customer_id', $order->customer_id)->get()->map(fn ($p) => [...$p->toArray(), 'available' => $p->amount - (int) Allocation::where('payment_id', $p->id)->sum('amount')]), 'previous' => $previous, 'due' => $invoice ? Billing::balance($invoice) : null, 'allocations' => $invoice ? Allocation::where('invoice_id', $invoice->id)->get() : [], 'credits' => $invoice ? Credit::where('invoice_id', $invoice->id)->get()->map(fn ($c) => [...$c->toArray(), 'refundable' => Billing::refundable($c)]) : [], 'refunds' => $invoice ? Refund::whereIn('credit_id', Credit::select('id')->where('invoice_id', $invoice->id))->get() : [], 'events' => AuditEvent::where(fn ($q) => $q->where('entity', 'orders')->where('entity_id', $order->id))->orWhere(fn ($q) => $q->where('entity', 'invoices')->where('entity_id', $invoice->id ?? 0))->latest('id')->get()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function orderInput(Request $r): array
    {
        return $r->validate(['customer_id' => 'required|exists:customers,id', 'promised_at' => 'required|date', 'notes' => 'nullable|string|max:4000', 'internal_notes' => 'nullable|string|max:4000', 'discount_type' => 'required|in:fixed,percent', 'discount_value' => 'required', 'lines' => 'required|array|min:1|max:100', 'lines.*.product_id' => 'required|exists:products,id', 'lines.*.quantity' => 'required|integer|min:1|max:1000', 'lines.*.price' => 'nullable', 'lines.*.discount_type' => 'required|in:fixed,percent', 'lines.*.discount_value' => 'required', 'lines.*.measurement_mode' => 'required|in:shared,per_piece', 'lines.*.instructions' => 'nullable|string|max:2000']);
    }

    public function createOrder(Request $r): RedirectResponse
    {
        $o = Orders::create($this->orderInput($r));

        return redirect('/studio/orders/'.$o->id)->with('success', 'Order created. Enter measurements, then confirm.');
    }

    public function editOrder(Request $r, int $order): RedirectResponse
    {
        $d = $this->orderInput($r);
        $d += $r->validate(['version' => 'required|integer', 'reset_measurements' => 'required|accepted']);
        Orders::editDraft($order, $d);

        return back()->with('success', 'Draft updated. Measurements reset for deliberate re-entry.');
    }

    public function copyPieces(Request $r, int $order, int $snapshot): RedirectResponse
    {
        $d = $r->validate(['version' => 'required|integer', 'reason' => 'nullable|string|max:2000']);
        Orders::copyToPieces($order, $snapshot, $d);

        return back()->with('success', 'Measurements independently copied to the other pieces.');
    }

    public function preview(Request $r): JsonResponse
    {
        $d = $this->orderInput($r);
        $lines = [];
        foreach ($d['lines'] as $l) {
            $p = Product::where('id', $l['product_id'])->firstOrFail();
            $price = CustomerPrice::where(['customer_id' => $d['customer_id'], 'product_id' => $p->id, 'active' => true])->value('price') ?? $p->price;
            $lines[] = ['quantity' => (int) $l['quantity'], 'unit_price' => isset($l['price']) && $l['price'] !== '' ? Pricing::minor($l['price']) : $price, 'discount_type' => $l['discount_type'], 'discount_value' => Pricing::minor($l['discount_value']), 'taxes' => $p->taxes];
        }

        return response()->json(Pricing::calculate($lines, $d['discount_type'], Pricing::minor($d['discount_value'])));
    }

    public function transition(Request $r, int $order): RedirectResponse
    {
        $d = $r->validate(['version' => 'required|integer', 'action' => 'required|in:confirm,Cutting,Stitching,Ready,Delivered,cancel,reschedule', 'reason' => 'nullable|string|max:2000', 'promised_at' => 'required_if:action,reschedule|date']);
        Orders::transition($order, $d);

        return back()->with('success', 'Order updated.');
    }

    public function snapshot(Request $r, int $order, int $snapshot): RedirectResponse
    {
        $d = $r->validate(['version' => 'required|integer', 'values' => 'required|array|max:150', 'reason' => 'nullable|string|max:2000', 'source_snapshot_id' => 'nullable|integer']);
        Orders::saveSnapshot($order, $snapshot, $d);

        return back()->with('success', 'Measurements saved.');
    }

    public function repeat(Request $r, int $order): RedirectResponse
    {
        $d = $r->validate(['pricing' => 'required|in:previous,current', 'promised_at' => 'required|date']);
        $o = Orders::repeat($order, $d);

        return redirect('/studio/orders/'.$o->id)->with('success', 'Independent repeat order created. Review measurements before confirmation.');
    }

    public function finance(Request $r, string $action, int $id = 0): RedirectResponse
    {
        $common = ['key' => 'required|uuid', 'version' => 'required_unless:action,invoice,payment|integer|min:1', 'effective_at' => 'required_unless:action,invoice|date', 'reason' => 'nullable|required_if:action,credit,refund,reverse,settle|string|max:2000'];
        $r->merge(['action' => $action]);
        $extra = match ($action) {
            'invoice' => [],'post' => [],'payment' => ['customer_id' => 'required|exists:customers,id', 'payment_method_id' => 'required|exists:payment_methods,id', 'amount' => 'required', 'settlement' => 'required|in:pending,settled', 'external_reference' => 'nullable|string|max:200', 'invoice_id' => 'nullable|exists:invoices,id', 'invoice_version' => 'required_with:invoice_id|integer|min:1'],'allocate' => ['invoice_id' => 'required|exists:invoices,id', 'invoice_version' => 'required|integer|min:1', 'amount' => 'required'],'settle' => [],'reverse' => [],'credit' => ['quantities' => 'required|array|max:100', 'quantities.*' => 'integer|min:0|max:10000'],'refund' => ['amount' => 'required', 'payment_method_id' => 'required|exists:payment_methods,id'],default => abort(404)
        };
        $d = $r->validate([...$common, ...$extra, 'action' => 'required|string']);
        unset($d['action']);
        match ($action) {
            'invoice' => Billing::invoice($id, $d),'post' => Billing::post($id, $d),'payment' => Billing::payment($d),'allocate' => Billing::allocate($id, $d),'settle' => Billing::settle($id, $d),'reverse' => Billing::reverse($id, $d),'credit' => Billing::credit($id, $d),'refund' => Billing::refund($id, $d)
        };

        return back()->with('success', 'Financial transaction recorded.');
    }

    public function followup(Request $r, ?int $id = null): RedirectResponse
    {
        $d = $r->validate(['customer_id' => 'required|exists:customers,id', 'due_at' => 'required|date', 'note' => 'required|string|max:2000', 'completed' => 'boolean', 'version' => $id ? 'required|integer' : 'nullable']);
        DB::transaction(function () use ($d, $id) {
            $m = $id ? Orders::lock(FollowUp::class, $id, $d['version']) : new FollowUp;
            $m->customer_id = $d['customer_id'];
            $m->due_at = Orders::date($d['due_at']);
            $m->note = $d['note'];
            $m->completed_at = ($d['completed'] ?? false) ? now() : null;
            if ($id) {
                Orders::touch($m);
            } else {
                $m->save();
            }Orders::audit($m, 'followup_saved');
        });

        return back()->with('success', 'Follow-up saved.');
    }

    /** @return array<string, mixed> */
    private function reportFilters(Request $r): array
    {
        return $r->validate([
            'type' => 'nullable|in:orders,items,customers,payments,discounts,delivery,outstanding,credits,profit,daily,weekly,monthly,invoices,revenue,cashflow',
            'q' => 'nullable|string|max:100',
            'stage' => 'nullable|in:New,Cutting,Stitching,Ready,Delivered,Cancelled',
            'late' => 'nullable|boolean',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'product_id' => 'nullable|integer|exists:products,id',
            'billing_status' => 'nullable|in:unbilled,draft,posted',
            'payment_status' => 'nullable|in:unpaid,partial,paid,credited',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'cutoff' => 'nullable|date',
            'order_from' => 'nullable|date',
            'order_to' => 'nullable|date|after_or_equal:order_from',
            'invoice_from' => 'nullable|date',
            'invoice_to' => 'nullable|date|after_or_equal:invoice_from',
            'payment_from' => 'nullable|date',
            'payment_to' => 'nullable|date|after_or_equal:payment_from',
            'promised_from' => 'nullable|date',
            'promised_to' => 'nullable|date|after_or_equal:promised_from',
            'delivered_from' => 'nullable|date',
            'delivered_to' => 'nullable|date|after_or_equal:delivered_from',
        ]);
    }

    private function reportType(string $type): void
    {
        abort_unless(in_array($type, ['orders', 'items', 'customers', 'payments', 'discounts', 'delivery', 'outstanding', 'credits', 'profit', 'daily', 'weekly', 'monthly', 'invoices', 'revenue', 'cashflow']), 404);
        if ($type === 'profit' && ! BusinessSetting::findOrFail(1)->cost_reporting) {
            Pricing::fail('Enable direct-cost reporting in business settings first.');
        }
    }

    public function export(Request $r, string $type): RedirectResponse
    {
        $this->reportType($type);
        $format = $r->validate(['format' => 'sometimes|in:csv,xlsx'])['format'] ?? 'csv';
        $filters = $this->reportFilters($r);
        $job = ReportExport::create(['user_id' => $r->user()->id, 'report' => $type, 'format' => $format, 'filters' => $filters]);
        GenerateReport::dispatch($job->id);

        return redirect('/studio/exports')->with('success', 'Export queued. This page refreshes while reports are processing.');
    }

    public function download(Request $r, int $id): StreamedResponse
    {
        $job = ReportExport::where('user_id', $r->user()->id)->findOrFail($id);
        abort_unless($job->state === 'ready' && Storage::disk('local')->exists($job->path), 404);

        return Storage::disk('local')->download($job->path, $job->report.'.'.$job->format);
    }

    public function retryExport(Request $r, int $id): RedirectResponse
    {
        $job = ReportExport::where('user_id', $r->user()->id)->findOrFail($id);
        abort_unless($job->state === 'failed', 422);
        $job->update(['state' => 'queued', 'error' => null]);
        GenerateReport::dispatch($job->id);

        return back()->with('success', 'Export queued again.');
    }

    public function reportPdf(Request $r, string $type): \Illuminate\Http\Response
    {
        $this->reportType($type);

        return Documents::reportPdf($type, $this->reportFilters($r));
    }

    public function document(Request $r, string $type, int $id): \Illuminate\Http\Response
    {
        return Documents::pdf($type, $id, $r->input('scope', 'orders'));
    }
}
