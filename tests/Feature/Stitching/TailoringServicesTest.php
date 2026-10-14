<?php

namespace Tests\Feature\Stitching;

use App\Domain\Orders;
use App\Models\Customer;
use App\Models\MeasurementTemplate;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\StudioSeeder;
use Database\Seeders\TailoringServicesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TailoringServicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StudioSeeder::class);
        $this->seed(TailoringServicesSeeder::class);
    }

    public function test_all_four_tailoring_services_and_templates_exist(): void
    {
        $services = ['شلوار قمیض', 'ویسٹ کوٹ', 'پینٹ', 'کوٹ / شیروانی'];

        foreach ($services as $serviceName) {
            $product = Product::where('name', $serviceName)->first();
            $this->assertNotNull($product, "Product {$serviceName} should exist");
            $this->assertTrue((bool) $product->active);
            $this->assertNotNull($product->template_id);

            $template = MeasurementTemplate::find($product->template_id);
            $this->assertNotNull($template, "Template for {$serviceName} should exist");
            $this->assertTrue((bool) $template->active);
        }
    }

    public function test_shalwar_qameez_has_exact_32_points_and_correct_numbering(): void
    {
        $template = MeasurementTemplate::where('code', 'shalwar_kameez')->firstOrFail();
        $fields = $template->fields;

        // Basic numeric measurements 1 to 16
        for ($i = 1; $i <= 16; $i++) {
            $f = collect($fields)->first(fn ($item) => str_starts_with($item['label'], "{$i}. "));
            $this->assertNotNull($f, "Shalwar Qameez point {$i} must exist");
            $this->assertSame('numeric', $f['type']);
            $this->assertSame('inches', $f['unit']);
        }

        // Pockets 17, 18, 19
        foreach ([17 => 'فرنٹ پاکٹ', 18 => 'سائیڈ پاکٹ', 19 => 'شلوار پاکٹ'] as $num => $name) {
            $f = collect($fields)->first(fn ($item) => str_starts_with($item['label'], "{$num}. "));
            $this->assertNotNull($f, "Shalwar Qameez pocket {$num} must exist");
            $this->assertSame('choice', $f['type']);
        }

        // 20 Extra measurement
        $f20 = collect($fields)->first(fn ($item) => str_starts_with($item['label'], '20. '));
        $this->assertNotNull($f20);
        $this->assertSame('numeric', $f20['type']);

        // Styles 21 to 32
        for ($i = 21; $i <= 32; $i++) {
            $f = collect($fields)->first(fn ($item) => str_starts_with($item['label'], "{$i}. "));
            $this->assertNotNull($f, "Shalwar Qameez style {$i} must exist");
            $this->assertSame('choice', $f['type']);

            // Must have corresponding note field
            $note = collect($fields)->firstWhere('depends_on', $f['code']);
            $this->assertNotNull($note, "Style {$i} must have a note field");
            $this->assertSame('text', $note['type']);
        }
    }

    public function test_waistcoat_has_exact_12_points(): void
    {
        $template = MeasurementTemplate::where('code', 'waistcoat')->firstOrFail();
        $fields = $template->fields;

        for ($i = 1; $i <= 6; $i++) {
            $f = collect($fields)->first(fn ($item) => str_contains($item['label'], match ($i) {
                1 => '۱۔ ', 2 => '۲۔ ', 3 => '۳۔ ', 4 => '۴۔ ', 5 => '۵۔ ', 6 => '۶۔ '
            }));
            $this->assertNotNull($f, "Waistcoat point {$i} must exist");
            $this->assertSame('numeric', $f['type']);
        }

        $f12 = collect($fields)->first(fn ($item) => str_contains($item['label'], '۱۲۔ ') || str_contains($item['label'], '12. '));
        $this->assertNotNull($f12);
        $this->assertSame('text', $f12['type']);
    }

    public function test_pant_has_exact_17_points(): void
    {
        $template = MeasurementTemplate::where('code', 'pant')->firstOrFail();
        $fields = $template->fields;

        for ($i = 1; $i <= 6; $i++) {
            $f = collect($fields)->first(fn ($item) => str_contains($item['label'], match ($i) {
                1 => '۱۔ ', 2 => '۲۔ ', 3 => '۳۔ ', 4 => '۴۔ ', 5 => '۵۔ ', 6 => '۶۔ '
            }));
            $this->assertNotNull($f, "Pant point {$i} must exist");
            $this->assertSame('numeric', $f['type']);
        }

        $f17 = collect($fields)->first(fn ($item) => str_contains($item['label'], '۱۷۔ ') || str_contains($item['label'], '17. '));
        $this->assertNotNull($f17);
        $this->assertSame('text', $f17['type']);
    }

    public function test_coat_sherwani_has_exact_26_points(): void
    {
        $template = MeasurementTemplate::where('code', 'coat_sherwani')->firstOrFail();
        $fields = $template->fields;

        for ($i = 1; $i <= 9; $i++) {
            $f = collect($fields)->first(fn ($item) => str_contains($item['label'], match ($i) {
                1 => '۱۔ ', 2 => '۲۔ ', 3 => '۳۔ ', 4 => '۴۔ ', 5 => '۵۔ ', 6 => '۶۔ ', 7 => '۷۔ ', 8 => '۸۔ ', 9 => '۹۔ '
            }));
            $this->assertNotNull($f, "Coat/Sherwani point {$i} must exist");
            $this->assertSame('numeric', $f['type']);
        }

        for ($i = 10; $i <= 26; $i++) {
            $f = collect($fields)->first(fn ($item) => str_contains($item['label'], match ($i) {
                10 => '۱۰۔ ', 11 => '۱۱۔ ', 12 => '۱۲۔ ', 13 => '۱۳۔ ', 14 => '۱۴۔ ', 15 => '۱۵۔ ', 16 => '۱۶۔ ',
                17 => '۱۷۔ ', 18 => '۱۸۔ ', 19 => '۱۹۔ ', 20 => '۲۰۔ ', 21 => '۲۱۔ ', 22 => '۲۲۔ ', 23 => '۲۳۔ ',
                24 => '۲۴۔ ', 25 => '۲۵۔ ', 26 => '۲۶۔ '
            }));
            $this->assertNotNull($f, "Coat/Sherwani style {$i} must exist");
            $this->assertSame('choice', $f['type']);
        }
    }

    public function test_multiple_services_render_one_page_per_service_in_orders_pdf(): void
    {
        $u = User::factory()->create(['is_owner' => true]);
        $this->actingAs($u);

        $customer = Customer::create([
            'name' => 'محمد رضوان',
            'phone' => '03001234567',
            'normalized_phone' => '+923001234567',
            'active' => true,
        ]);

        $pant = Product::where('name', 'پینٹ')->firstOrFail();
        $sk = Product::where('name', 'شلوار قمیض')->firstOrFail();
        $wc = Product::where('name', 'ویسٹ کوٹ')->firstOrFail();

        $order = Orders::create([
            'customer_id' => $customer->id,
            'promised_at' => now()->addDays(5)->toIso8601String(),
            'discount_type' => 'fixed',
            'discount_value' => '0',
            'lines' => [
                ['product_id' => $pant->id, 'quantity' => 1, 'discount_type' => 'fixed', 'discount_value' => '0', 'measurement_mode' => 'shared'],
                ['product_id' => $sk->id, 'quantity' => 1, 'discount_type' => 'fixed', 'discount_value' => '0', 'measurement_mode' => 'shared'],
                ['product_id' => $wc->id, 'quantity' => 1, 'discount_type' => 'fixed', 'discount_value' => '0', 'measurement_mode' => 'shared'],
            ],
        ]);

        foreach (['order', 'tailor', 'measurements'] as $docType) {
            $response = $this->get("/studio-document/{$docType}/{$order->id}");
            $response->assertOk();
            $this->assertStringStartsWith('%PDF', $response->getContent());
            $pageCount = preg_match_all('/\/Type\s*\/Page[^s]/', $response->getContent());
            $this->assertSame(3, $pageCount, "PDF for {$docType} must have exactly 3 pages for 3 services");
        }
    }
}
