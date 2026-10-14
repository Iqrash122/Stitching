<?php

namespace Tests\Feature\Stitching;

use App\Domain\Billing;
use App\Domain\Orders;
use App\Domain\Reports;
use App\Models\Allocation;
use App\Models\Credit;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MeasurementSnapshot;
use App\Models\MeasurementTemplate;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Refund;
use App\Models\StitchingOrder;
use App\Models\User;
use Database\Seeders\StudioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StudioSeeder::class);
        $u = User::factory()->create();
        $u->forceFill(['is_owner' => true])->save();
        $this->actingAs($u);
    }

    private function customer(): Customer
    {
        return Customer::create(['name' => 'Ayesha', 'phone' => '03001234567', 'normalized_phone' => '+923001234567', 'active' => true]);
    }

    private function order(int $price = 220000, int $quantity = 1, string $mode = 'shared'): StitchingOrder
    {
        $c = $this->customer();
        $t = MeasurementTemplate::where('code', 'kurta')->firstOrFail();
        $p = Product::create(['name' => 'Kurta stitching', 'template_id' => $t->id, 'price' => $price, 'taxes' => [], 'cost_known' => true, 'cost' => 0]);

        return Orders::create(['customer_id' => $c->id, 'promised_at' => now()->addDay()->toIso8601String(), 'discount_type' => 'fixed', 'discount_value' => '0', 'lines' => [['product_id' => $p->id, 'quantity' => $quantity, 'discount_type' => 'fixed', 'discount_value' => '0', 'measurement_mode' => $mode]]]);
    }

    private function measure(StitchingOrder $o, string $chest = '42'): void
    {
        foreach ($o->lines()->with('snapshots')->get() as $l) {
            foreach ($l->snapshots as $s) {
                $values = [];
                foreach ($s->fields as $f) {
                    $values[$f['code']] = $f['type'] === 'numeric' ? $chest : ($f['type'] === 'choice' ? 'no' : '');
                }Orders::saveSnapshot($o->id, $s->id, ['version' => $o->fresh()->version, 'values' => $values]);
            }
        }
    }

    private function posted(StitchingOrder $o): Invoice
    {
        $this->measure($o);
        Orders::transition($o->id, ['version' => $o->fresh()->version, 'action' => 'confirm']);
        $id = Billing::invoice($o->id, ['key' => (string) Str::uuid()])['id'];
        $i = Invoice::findOrFail($id);
        Billing::post($i->id, ['key' => (string) Str::uuid(), 'version' => $i->version, 'effective_at' => now()->toIso8601String()]);

        return $i->fresh();
    }

    private function pay(Invoice $i, string $amount, ?string $key = null): array
    {
        return Billing::payment(['key' => $key ?? (string) Str::uuid(), 'customer_id' => $i->customer_id, 'invoice_id' => $i->id, 'invoice_version' => $i->fresh()->version, 'amount' => $amount, 'payment_method_id' => 1, 'settlement' => 'settled', 'effective_at' => now()->toIso8601String()]);
    }

    public function test_starter_seeders_do_not_duplicate_or_overwrite_owner_edits(): void
    {
        $t = MeasurementTemplate::first();
        $t->name = 'Owner edited';
        $t->save();
        $this->seed(StudioSeeder::class);
        $this->assertSame(9, MeasurementTemplate::count());
        $this->assertSame('Owner edited', $t->fresh()->name);
        $this->assertSame(0, Product::count());
    }

    public function test_owner_pages_and_registration_are_protected(): void
    {
        $this->get('/studio')->assertOk();
        $this->get('/register')->assertNotFound();
        $this->actingAs(User::factory()->create())->get('/studio/orders')->assertForbidden();
        auth()->logout();
        $this->get('/studio/orders')->assertRedirect('/login');
    }

    public function test_customer_creation_normalization_and_duplicate_suggestion(): void
    {
        $this->post('/studio/customers', ['name' => 'Ali', 'phone' => '03001234567', 'active' => true])->assertRedirect();
        $this->assertDatabaseHas('customers', ['normalized_phone' => '+923001234567']);
        $this->post('/studio/customers', ['name' => 'Family member', 'phone' => '03001234567', 'active' => true])->assertSessionHas('success', fn ($v) => str_contains($v, 'shares'));
        $this->assertSame(2, Customer::count());
    }

    public function test_confirmation_blocks_incomplete_measurements(): void
    {
        $o = $this->order();
        $this->expectException(ValidationException::class);
        Orders::transition($o->id, ['version' => $o->version, 'action' => 'confirm']);
    }

    public function test_per_piece_coverage_and_zero_measurement(): void
    {
        $template = MeasurementTemplate::where('code', 'kurta')->firstOrFail();
        $template->fields = array_map(function (array $field): array {
            if ($field['type'] === 'numeric') {
                $field['min'] = '0';
            }

            return $field;
        }, $template->fields);
        $template->save();

        $o = $this->order(10000, 3, 'per_piece');
        $this->assertSame(3, MeasurementSnapshot::count());
        $this->measure($o, '0');
        Orders::transition($o->id, ['version' => $o->fresh()->version, 'action' => 'confirm']);
        $this->assertNotNull($o->fresh()->confirmed_at);
        $this->assertTrue(MeasurementSnapshot::first()->fields[0]['is_set']);
        $this->assertSame('0', MeasurementSnapshot::first()->fields[0]['value']);
    }

    public function test_partial_payment_example_and_paid_delivery(): void
    {
        $o = $this->order();
        $i = $this->posted($o);
        $this->pay($i, '1000');
        $this->assertSame(120000, Billing::balance($i));
        $this->pay($i, '500');
        $this->assertSame(70000, Billing::balance($i));
        $this->pay($i, '700');
        $this->assertSame(0, Billing::balance($i));
        foreach (['Cutting', 'Stitching', 'Ready', 'Delivered'] as $stage) {
            Orders::transition($o->id, ['version' => $o->fresh()->version, 'action' => $stage]);
        }$this->assertSame('Delivered', $o->fresh()->stage);
        $this->assertSame(3, Payment::count());
        $this->assertSame(3, Allocation::count());
    }

    public function test_unpaid_delivery_is_blocked(): void
    {
        $o = $this->order();
        $this->posted($o);
        foreach (['Cutting', 'Stitching', 'Ready'] as $stage) {
            Orders::transition($o->id, ['version' => $o->fresh()->version, 'action' => $stage]);
        }$this->expectException(ValidationException::class);
        Orders::transition($o->id, ['version' => $o->fresh()->version, 'action' => 'Delivered']);
    }

    public function test_payment_replay_and_payload_mismatch(): void
    {
        $o = $this->order();
        $i = $this->posted($o);
        $d = ['key' => (string) Str::uuid(), 'customer_id' => $i->customer_id, 'invoice_id' => $i->id, 'invoice_version' => $i->version, 'amount' => '1000', 'payment_method_id' => 1, 'settlement' => 'settled', 'effective_at' => now()->toIso8601String()];
        $a = Billing::payment($d);
        $this->assertSame($a, Billing::payment($d));
        $this->assertSame(1, Payment::count());
        $d['amount'] = '999';
        $this->expectException(ValidationException::class);
        Billing::payment($d);
    }

    public function test_overpayment_is_rejected_without_creating_receipt(): void
    {
        $o = $this->order();
        $i = $this->posted($o);
        try {
            $this->pay($i, '2201');
            $this->fail('Expected overpayment rejection');
        } catch (ValidationException) {
            $this->assertSame(0, Payment::count());
            $this->assertSame(220000, Billing::balance($i));
        }
    }

    public function test_pending_bank_funds_and_unallocated_advances_do_not_pay_invoice(): void
    {
        $o = $this->order();
        $i = $this->posted($o);
        Billing::payment(['key' => (string) Str::uuid(), 'customer_id' => $i->customer_id, 'amount' => '2200', 'payment_method_id' => 2, 'settlement' => 'pending', 'effective_at' => now()->toIso8601String()]);
        $this->assertSame(220000, Billing::balance($i));
        $p = Payment::first();
        Billing::settle($p->id, ['key' => (string) Str::uuid(), 'version' => $p->version, 'effective_at' => now()->toIso8601String(), 'reason' => 'Bank statement checked']);
        Billing::allocate($p->id, ['key' => (string) Str::uuid(), 'version' => $p->fresh()->version, 'invoice_id' => $i->id, 'invoice_version' => $i->version, 'amount' => '2200', 'effective_at' => now()->toIso8601String()]);
        $this->assertSame(0, Billing::balance($i));
    }

    public function test_full_credit_applies_unpaid_part_before_refund(): void
    {
        $o = $this->order();
        $i = $this->posted($o);
        $this->pay($i, '1000');
        $c = Billing::credit($i->id, ['key' => (string) Str::uuid(), 'version' => $i->fresh()->version, 'quantities' => [$o->lines()->first()->id => 1], 'effective_at' => now()->toIso8601String(), 'reason' => 'Customer cancellation']);
        $credit = Credit::findOrFail($c['id']);
        $this->assertSame(0, Billing::balance($i));
        $this->assertSame(100000, Billing::refundable($credit));
        Billing::refund($credit->id, ['key' => (string) Str::uuid(), 'version' => $i->fresh()->version, 'amount' => '1000', 'payment_method_id' => 1, 'effective_at' => now()->toIso8601String(), 'reason' => 'Returned cash']);
        $this->assertSame(0, Billing::refundable($credit));
        $this->assertSame(1, Refund::count());
    }

    public function test_repeat_and_confirmed_revision_preserve_old_42(): void
    {
        $o = $this->order();
        $this->posted($o);
        $old = MeasurementSnapshot::first();
        $new = Orders::repeat($o->id, ['pricing' => 'previous', 'promised_at' => now()->addDays(3)->toIso8601String()]);
        $this->measure($new, '44');
        $this->assertSame('42', $old->fresh()->fields[0]['value']);
        $this->assertNull($new->confirmed_at);
        $s = $new->lines()->first()->snapshots()->first();
        $this->assertSame('44', $s->fields[0]['value']);
        $values = array_column($old->fields, 'value', 'code');
        $values[array_key_first($values)] = '45';
        Orders::saveSnapshot($o->id, $old->id, ['version' => $o->fresh()->version, 'values' => $values, 'reason' => 'Customer remeasurement']);
        $this->assertFalse($old->fresh()->current);
        $this->assertSame('42', $old->fresh()->fields[0]['value']);
    }

    public function test_cancellation_preserves_debt_and_rescheduling_preserves_original(): void
    {
        $o = $this->order();
        $i = $this->posted($o);
        $promise = $o->fresh()->original_promised_at;
        Orders::transition($o->id, ['version' => $o->fresh()->version, 'action' => 'reschedule', 'promised_at' => now()->addDays(8)->toIso8601String(), 'reason' => 'Customer request']);
        $this->assertTrue($promise->equalTo($o->fresh()->original_promised_at));
        Orders::transition($o->id, ['version' => $o->fresh()->version, 'action' => 'cancel', 'reason' => 'Stopped production']);
        $this->assertSame(220000, Billing::balance($i));
    }

    public function test_invoice_pdf_excludes_internal_sentinel(): void
    {
        $o = $this->order();
        $o->internal_notes = 'PRIVATE_SENTINEL_9281';
        $o->save();
        $i = $this->posted($o);
        $response = $this->get('/studio-document/invoice/'.$i->id);
        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringNotContainsString('PRIVATE_SENTINEL_9281', $response->getContent());
    }

    public function test_stale_workflow_request_rejected(): void
    {
        $o = $this->order();
        $this->measure($o);
        $this->expectException(ValidationException::class);
        Orders::transition($o->id, ['version' => 1, 'action' => 'confirm']);
    }

    public function test_order_detail_and_reports_render_with_real_data(): void
    {
        $o = $this->order();
        $this->posted($o);
        foreach (['/studio/orders/'.$o->id, '/studio/customers/'.$o->customer_id, '/studio/billing', '/studio/reports?type=outstanding', '/studio/templates', '/studio/configuration'] as $url) {
            $this->get($url)->assertOk();
        }$rows = Reports::rows('outstanding', []);
        $this->assertSame(220000, $rows[0]['due_minor']);
    }

    public function test_customer_measurement_search_finds_saved_size_by_name_phone_and_order(): void
    {
        $order = $this->order();
        $this->measure($order);
        Orders::transition($order->id, ['version' => $order->fresh()->version, 'action' => 'confirm']);

        foreach (['Ayesha', '03001234567', $order->reference] as $search) {
            $this->get('/studio/measurement-search?q='.urlencode($search))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('studio/index')
                    ->where('section', 'measurement-search')
                    ->where('measurement_rows.data.0.name', 'Ayesha')
                    ->where('measurement_rows.data.0.measurements.0.source_order', $order->reference)
                    ->has('measurement_rows.data.0.measurements.0.pieces.0.fields')
                );
        }
    }

    public function test_multiple_stitching_items_share_one_invoice_and_tailor_sheet(): void
    {
        $customer = $this->customer();
        $shalwarKameez = MeasurementTemplate::where('code', 'shalwar_kameez')->firstOrFail();
        $pant = MeasurementTemplate::where('code', 'pant')->firstOrFail();
        $sk = Product::create(['name' => 'Shalwar Kameez stitching', 'template_id' => $shalwarKameez->id, 'price' => 250000, 'taxes' => [], 'cost_known' => true, 'cost' => 0]);
        $p = Product::create(['name' => 'Pant stitching', 'template_id' => $pant->id, 'price' => 180000, 'taxes' => [], 'cost_known' => true, 'cost' => 0]);

        $order = Orders::create([
            'customer_id' => $customer->id,
            'promised_at' => now()->addDay()->toIso8601String(),
            'discount_type' => 'fixed',
            'discount_value' => '0',
            'lines' => [
                ['product_id' => $sk->id, 'quantity' => 1, 'discount_type' => 'fixed', 'discount_value' => '0', 'measurement_mode' => 'shared', 'instructions' => 'Standard fitting'],
                ['product_id' => $p->id, 'quantity' => 1, 'discount_type' => 'fixed', 'discount_value' => '0', 'measurement_mode' => 'shared', 'instructions' => 'Straight bottom'],
            ],
        ]);

        $this->measure($order);
        Orders::transition($order->id, ['version' => $order->fresh()->version, 'action' => 'confirm']);
        $invoiceId = Billing::invoice($order->id, ['key' => (string) Str::uuid()])['id'];
        $invoice = Invoice::findOrFail($invoiceId);

        $this->assertCount(2, $invoice->lines);
        $this->assertSame(['Shalwar Kameez stitching', 'Pant stitching'], array_column($invoice->lines, 'description'));
        $this->assertSame(430000, $invoice->totals['total']);

        $response = $this->get('/studio-document/tailor/'.$order->id);
        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertSame(2, preg_match_all('/\/Type\s*\/Page[^s]/', $response->getContent()));
    }

    public function test_posting_rejects_changed_invoice_lines(): void
    {
        $o = $this->order();
        $this->measure($o);
        Orders::transition($o->id, ['version' => $o->fresh()->version, 'action' => 'confirm']);
        $id = Billing::invoice($o->id, ['key' => (string) Str::uuid()])['id'];
        $invoice = Invoice::findOrFail($id);
        $lines = $invoice->lines;
        $lines[0]['description'] = 'Altered garment';
        $invoice->update(['lines' => $lines]);
        $this->expectException(ValidationException::class);
        Billing::post($id, ['key' => (string) Str::uuid(), 'version' => $invoice->version, 'effective_at' => now()->toIso8601String()]);
    }
}
