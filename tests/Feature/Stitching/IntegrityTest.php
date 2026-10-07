<?php

namespace Tests\Feature\Stitching;

use App\Domain\Billing;
use App\Domain\Documents;
use App\Domain\Orders;
use App\Domain\Reports;
use App\Jobs\GenerateReport;
use App\Models\Allocation;
use App\Models\BusinessSetting;
use App\Models\Credit;
use App\Models\Customer;
use App\Models\CustomerPrice;
use App\Models\Invoice;
use App\Models\MeasurementSnapshot;
use App\Models\MeasurementTemplate;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ReportExport;
use App\Models\StitchingOrder;
use App\Models\User;
use Database\Seeders\StudioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StudioSeeder::class);
        $this->actingAs(User::factory()->create(['is_owner' => true]));
        Carbon::setTestNow('2026-09-14T12:00:00Z');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function order(int $qty = 1, string $mode = 'shared'): StitchingOrder
    {
        $c = Customer::create(['name' => 'Sara', 'phone' => '03001234567', 'normalized_phone' => '+923001234567']);
        $p = Product::create(['name' => 'Service', 'template_id' => MeasurementTemplate::where('code', 'kurta')->value('id'), 'price' => 10000, 'taxes' => []]);

        return Orders::create(['customer_id' => $c->id, 'promised_at' => now()->addDay()->toIso8601String(), 'discount_type' => 'fixed', 'discount_value' => '0', 'lines' => [['product_id' => $p->id, 'quantity' => $qty, 'measurement_mode' => $mode]]]);
    }

    private function fill(StitchingOrder $o): void
    {
        foreach ($o->lines()->with('snapshots')->get() as $l) {
            foreach ($l->snapshots as $s) {
                $v = [];
                foreach ($s->fields as $f) {
                    $v[$f['code']] = $f['type'] === 'numeric' ? '42' : ($f['type'] === 'choice' ? 'no' : '');
                }Orders::saveSnapshot($o->id, $s->id, ['version' => $o->fresh()->version, 'values' => $v]);
            }
        }
    }

    private function posted(StitchingOrder $o): Invoice
    {
        $this->fill($o);
        Orders::transition($o->id, ['version' => $o->fresh()->version, 'action' => 'confirm']);
        $id = Billing::invoice($o->id, ['key' => (string) Str::uuid()])['id'];
        Billing::post($id, ['key' => (string) Str::uuid(), 'version' => 1, 'effective_at' => now()->toIso8601String()]);

        return Invoice::findOrFail($id);
    }

    private function credit(Invoice $i, int $q)
    {
        return Billing::credit($i->id, ['key' => (string) Str::uuid(), 'version' => $i->fresh()->version, 'effective_at' => now()->toIso8601String(), 'reason' => 'Correction', 'quantities' => [$i->lines[0]['id'] => $q]]);
    }

    public function test_confirmed_order_blocks_backward_and_terminal_edits(): void
    {
        $o = $this->order();
        $this->posted($o);
        Orders::transition($o->id, ['version' => $o->fresh()->version, 'action' => 'Cutting']);
        $this->expectException(ValidationException::class);
        Orders::transition($o->id, ['version' => $o->fresh()->version, 'action' => 'New']);
    }

    public function test_incomplete_second_piece_prevents_confirmation(): void
    {
        $o = $this->order(2, 'per_piece');
        $s = $o->lines()->first()->snapshots()->first();
        $v = array_column($s->fields, 'value', 'code');
        $v['kurta_length'] = '40';
        Orders::saveSnapshot($o->id, $s->id, ['version' => $o->version, 'values' => $v]);
        $this->expectException(ValidationException::class);
        Orders::transition($o->id, ['version' => $o->fresh()->version, 'action' => 'confirm']);
    }

    public function test_copy_to_pieces_has_independent_values(): void
    {
        $o = $this->order(2, 'per_piece');
        $s = $o->lines()->first()->snapshots()->first();
        Orders::saveSnapshot($o->id, $s->id, ['version' => $o->version, 'values' => ['kurta_length' => '42']]);
        Orders::copyToPieces($o->id, $s->id, ['version' => $o->fresh()->version]);
        $other = $o->lines()->first()->snapshots()->where('id', '!=', $s->id)->first();
        Orders::saveSnapshot($o->id, $other->id, ['version' => $o->fresh()->version, 'values' => ['kurta_length' => '44']]);
        $this->assertSame('42', $s->fresh()->fields[0]['value']);
        $this->assertSame('44', $other->fresh()->fields[0]['value']);
    }

    public function test_draft_edit_requires_deliberate_reset_and_preserves_reference(): void
    {
        $o = $this->order();
        $this->fill($o);
        $d = ['version' => $o->fresh()->version, 'customer_id' => $o->customer_id, 'promised_at' => now()->addDays(2)->toIso8601String(), 'discount_type' => 'fixed', 'discount_value' => '0', 'lines' => [['product_id' => $o->lines()->first()->product_id, 'quantity' => 2, 'measurement_mode' => 'shared']]];
        try {
            Orders::editDraft($o->id, $d);
            $this->fail('Reset required');
        } catch (ValidationException) {
        }$d['reset_measurements'] = true;
        Orders::editDraft($o->id, $d);
        $this->assertSame($o->reference, $o->fresh()->reference);
        $this->assertSame(2, $o->lines()->first()->quantity);
        $this->assertFalse($o->lines()->first()->snapshots()->first()->fields[0]['is_set']);
    }

    public function test_archived_template_and_changed_labels_do_not_change_frozen_values(): void
    {
        $o = $this->order();
        $this->posted($o);
        $s = MeasurementSnapshot::first();
        $fields = $s->fields;
        $template = MeasurementTemplate::findOrFail($s->template_id);
        $template->fields = [];
        $template->active = false;
        $template->save();
        $this->assertSame($fields, $s->fresh()->fields);
    }

    public function test_foreign_customer_source_is_rejected(): void
    {
        $old = $this->order();
        $this->posted($old);
        $source = MeasurementSnapshot::first();
        $new = $this->order();
        $target = $new->lines()->first()->snapshots()->first();
        $this->expectException(ValidationException::class);
        Orders::saveSnapshot($new->id, $target->id, ['version' => $new->version, 'values' => ['kurta_length' => '42'], 'source_snapshot_id' => $source->id]);
    }

    public function test_cumulative_credit_limit_and_fraction_rounding(): void
    {
        $o = $this->order(3);
        $i = $this->posted($o);
        $this->credit($i, 1);
        $this->credit($i, 2);
        $this->assertSame($i->totals['total'], (int) Credit::sum('amount'));
        $this->assertSame(0, Billing::balance($i));
        $this->expectException(ValidationException::class);
        $this->credit($i, 1);
    }

    public function test_historical_balance_and_backdated_mutation_guard(): void
    {
        $o = $this->order();
        $i = $this->posted($o);
        $cutoff = now()->toIso8601String();
        Carbon::setTestNow(now()->addHour());
        Billing::payment(['key' => (string) Str::uuid(), 'customer_id' => $i->customer_id, 'invoice_id' => $i->id, 'invoice_version' => $i->version, 'amount' => '50', 'payment_method_id' => 1, 'settlement' => 'settled', 'effective_at' => now()->toIso8601String()]);
        $this->assertSame(10000, Reports::rows('outstanding', ['cutoff' => $cutoff])[0]['due_minor']);
        $this->assertSame(5000, Billing::balance($i));
        $this->expectException(ValidationException::class);
        Billing::credit($i->id, ['key' => (string) Str::uuid(), 'version' => $i->fresh()->version, 'effective_at' => $cutoff, 'reason' => 'Backdated', 'quantities' => [$i->lines[0]['id'] => 1]]);
    }

    public function test_pending_bank_allocation_direct_request_is_blocked(): void
    {
        $o = $this->order();
        $i = $this->posted($o);
        $this->postJson('/studio/finance/payment', ['key' => (string) Str::uuid(), 'customer_id' => $i->customer_id, 'invoice_id' => $i->id, 'invoice_version' => $i->version, 'amount' => '50', 'payment_method_id' => 2, 'settlement' => 'pending', 'effective_at' => now()->toIso8601String()])->assertUnprocessable();
        $this->assertSame(0, Payment::count());
    }

    public function test_credit_delivery_keeps_debt(): void
    {
        $o = $this->order();
        $i = $this->posted($o);
        BusinessSetting::findOrFail(1)->update(['credit_delivery' => true]);
        foreach (['Cutting', 'Stitching', 'Ready'] as $stage) {
            Orders::transition($o->id, ['version' => $o->fresh()->version, 'action' => $stage]);
        }Orders::transition($o->id, ['version' => $o->fresh()->version, 'action' => 'Delivered', 'reason' => 'Owner approved outstanding balance']);
        $this->assertSame(10000, Billing::balance($i));
        $this->assertSame('Delivered', $o->fresh()->stage);
    }

    public function test_strict_lateness_boundary_including_ready(): void
    {
        $o = $this->order();
        $this->posted($o);
        $o->refresh()->update(['promised_at' => now(), 'stage' => 'Ready']);
        $this->assertSame(0, Reports::orderQuery(['late' => true])->count());
        Carbon::setTestNow(now()->addSecond());
        $this->assertSame(1, Reports::orderQuery(['late' => true])->count());
    }

    public function test_reversal_restores_balance_and_preserves_receipt(): void
    {
        $o = $this->order();
        $i = $this->posted($o);
        Billing::payment(['key' => (string) Str::uuid(), 'customer_id' => $i->customer_id, 'invoice_id' => $i->id, 'invoice_version' => $i->version, 'amount' => '50', 'payment_method_id' => 1, 'settlement' => 'settled', 'effective_at' => now()->toIso8601String()]);
        $a = Allocation::first();
        Billing::reverse($a->id, ['key' => (string) Str::uuid(), 'version' => $i->fresh()->version, 'effective_at' => now()->toIso8601String(), 'reason' => 'Wrong invoice selected']);
        $this->assertSame(10000, Billing::balance($i));
        $this->assertSame(1, Payment::count());
        $this->assertSame(2, Allocation::count());
    }

    public function test_queued_exports_private_download_and_formula_safety(): void
    {
        $o = $this->order();
        $this->posted($o);
        $this->post('/studio-export/orders?format=xlsx')->assertRedirect('/studio/exports');
        $job = ReportExport::firstOrFail();
        (new GenerateReport($job->id))->handle();
        $this->assertSame('ready', $job->fresh()->state);
        $this->get('/studio-download/'.$job->id)->assertOk();
        $nonOwner = User::factory()->create();
        $this->actingAs($nonOwner)->get('/studio-download/'.$job->id)->assertForbidden();
        $path = Documents::writeExport([['name' => '=HYPERLINK("x")']], 'safety', 'csv');
        $this->assertStringContainsString("'=HYPERLINK", Storage::disk('local')->get($path));
        Storage::disk('local')->delete($path);
    }

    public function test_customer_specific_price_and_manual_override_do_not_change_default(): void
    {
        $o = $this->order();
        $p = Product::first();
        CustomerPrice::create(['customer_id' => $o->customer_id, 'product_id' => $p->id, 'price' => 8000, 'active' => true]);
        $data = ['customer_id' => $o->customer_id, 'promised_at' => now()->addDay()->toIso8601String(), 'lines' => [['product_id' => $p->id, 'quantity' => 1]]];
        $new = Orders::create($data);
        $this->assertSame(8000, $new->lines()->first()->unit_price);
        $this->assertSame('customer', $new->lines()->first()->price_source);
        $data['lines'][0]['price'] = '75';
        $manual = Orders::create($data);
        $this->assertSame(7500, $manual->lines()->first()->unit_price);
        $this->assertSame(10000, $p->fresh()->price);
    }

    public function test_returning_rate_denominator(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $o = $this->order();
            $o->update(['stage' => 'Delivered', 'delivered_at' => now(), 'confirmed_at' => now()]);
            if ($i < 65) {
                $copy = $o->replicate();
                $copy->reference = 'RET-'.$i;
                $copy->save();
            }
        }$d = Reports::dashboard('today');
        $this->assertSame(100, $d['population']);
        $this->assertSame(65, $d['returning']);
        $this->assertEquals(65, $d['returning_rate']);
    }

    public function test_report_filters_cover_product_workflow_and_business_dates(): void
    {
        $order = $this->order();
        $product = $order->lines()->firstOrFail()->product_id;
        $this->posted($order);
        Orders::transition($order->id, ['version' => $order->fresh()->version, 'action' => 'Cutting']);

        $filters = [
            'product_id' => $product,
            'stage' => 'Cutting',
            'order_from' => '2026-09-14T11:00:00Z',
            'order_to' => '2026-09-14T13:00:00Z',
            'promised_from' => '2026-09-15T11:00:00Z',
            'promised_to' => '2026-09-15T13:00:00Z',
        ];
        $this->assertCount(1, Reports::rows('orders', $filters));
        $this->assertCount(0, Reports::rows('orders', [...$filters, 'stage' => 'Ready']));
        $this->assertCount(0, Reports::rows('orders', [...$filters, 'order_from' => '2026-09-15T00:00:00Z']));
    }

    public function test_report_billing_and_historical_balance_status_filters(): void
    {
        $unbilled = $this->order();
        $unpaidInvoice = $this->posted($this->order());
        $partialInvoice = $this->posted($this->order());
        $paidInvoice = $this->posted($this->order());

        Billing::payment([
            'key' => (string) Str::uuid(),
            'customer_id' => $partialInvoice->customer_id,
            'invoice_id' => $partialInvoice->id,
            'invoice_version' => $partialInvoice->fresh()->version,
            'amount' => '50',
            'payment_method_id' => 1,
            'settlement' => 'settled',
            'effective_at' => now()->toIso8601String(),
        ]);
        Billing::payment([
            'key' => (string) Str::uuid(),
            'customer_id' => $paidInvoice->customer_id,
            'invoice_id' => $paidInvoice->id,
            'invoice_version' => $paidInvoice->fresh()->version,
            'amount' => '100',
            'payment_method_id' => 1,
            'settlement' => 'settled',
            'effective_at' => now()->toIso8601String(),
        ]);

        $this->assertSame($unbilled->reference, Reports::rows('orders', ['billing_status' => 'unbilled'])[0]['order']);
        $this->assertSame($unpaidInvoice->reference, Reports::rows('invoices', ['payment_status' => 'unpaid'])[0]['invoice']);
        $this->assertSame($partialInvoice->reference, Reports::rows('invoices', ['payment_status' => 'partial'])[0]['invoice']);
        $this->assertSame($paidInvoice->reference, Reports::rows('invoices', ['payment_status' => 'paid'])[0]['invoice']);
    }

    public function test_report_invoice_and_payment_date_filters_are_independent(): void
    {
        $invoice = $this->posted($this->order());
        Carbon::setTestNow('2026-09-15T12:00:00Z');
        Billing::payment([
            'key' => (string) Str::uuid(),
            'customer_id' => $invoice->customer_id,
            'invoice_id' => $invoice->id,
            'invoice_version' => $invoice->fresh()->version,
            'amount' => '50',
            'payment_method_id' => 1,
            'settlement' => 'settled',
            'effective_at' => now()->toIso8601String(),
        ]);

        $this->assertCount(1, Reports::rows('payments', [
            'invoice_from' => '2026-09-14T11:00:00Z',
            'invoice_to' => '2026-09-14T13:00:00Z',
            'payment_from' => '2026-09-15T11:00:00Z',
            'payment_to' => '2026-09-15T13:00:00Z',
        ]));
        $this->assertCount(0, Reports::rows('payments', [
            'payment_to' => '2026-09-14T23:59:59Z',
        ]));
    }

    public function test_customer_report_uses_order_invoice_and_payment_dimensions(): void
    {
        $order = $this->order();
        $productId = $order->lines()->firstOrFail()->product_id;
        $invoice = $this->posted($order);
        Carbon::setTestNow('2026-09-15T12:00:00Z');
        Billing::payment([
            'key' => (string) Str::uuid(),
            'customer_id' => $invoice->customer_id,
            'invoice_id' => $invoice->id,
            'invoice_version' => $invoice->fresh()->version,
            'amount' => '50',
            'payment_method_id' => 1,
            'settlement' => 'settled',
            'effective_at' => now()->toIso8601String(),
        ]);

        $rows = Reports::rows('customers', [
            'product_id' => $productId,
            'invoice_from' => '2026-09-14T11:00:00Z',
            'invoice_to' => '2026-09-14T13:00:00Z',
            'payment_from' => '2026-09-15T11:00:00Z',
            'payment_to' => '2026-09-15T13:00:00Z',
            'payment_status' => 'partial',
        ]);
        $this->assertCount(1, $rows);
        $this->assertSame($order->customer->name, $rows[0]['name']);
        $this->assertCount(0, Reports::rows('customers', ['product_id' => $productId + 9999]));
    }

    public function test_owner_internal_report_pdf_uses_the_report_filters(): void
    {
        $order = $this->order();
        $this->posted($order);

        $response = $this->get('/studio-report-pdf/orders?customer_id='.$order->customer_id);
        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
