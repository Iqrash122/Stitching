<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_owner')->default(false);
        });
        Schema::create('business_settings', function (Blueprint $t) {
            $t->id();
            $t->string('name')->default('Stitching Studio');
            $t->string('currency', 3)->default('PKR');
            $t->string('timezone')->default('Asia/Karachi');
            $t->string('country', 2)->default('PK');
            $t->text('address')->nullable();
            $t->string('phone')->nullable();
            $t->text('invoice_footer')->nullable();
            $t->string('logo')->nullable();
            $t->boolean('credit_delivery')->default(false);
            $t->boolean('cost_reporting')->default(false);
            $t->unsignedInteger('recent_days')->default(30);
            $t->unsignedInteger('refresh_seconds')->default(60);
            $t->text('whatsapp_template')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone');
            $t->string('normalized_phone')->index();
            $t->string('whatsapp')->nullable();
            $t->string('normalized_whatsapp')->nullable()->index();
            $t->text('address')->nullable();
            $t->text('notes')->nullable();
            $t->boolean('active')->default(true);
            $t->index('name');
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        Schema::create('templates', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->unsignedInteger('revision')->default(1);
            $t->string('unit')->default('inches');
            $t->json('fields');
            $t->text('notes')->nullable();
            $t->boolean('active')->default(true);
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('category')->nullable();
            $t->foreignId('template_id')->constrained('templates');
            $t->bigInteger('price')->default(0);
            $t->boolean('cost_known')->default(false);
            $t->bigInteger('cost')->default(0);
            $t->json('taxes');
            $t->boolean('active')->default(true);
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        Schema::create('customer_prices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained('customers');
            $t->foreignId('product_id')->constrained('products');
            $t->bigInteger('price');
            $t->boolean('active')->default(true);
            $t->unique(['customer_id', 'product_id']);
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        Schema::create('payment_methods', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('kind');
            $t->boolean('active')->default(true);
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->unique();
            $t->foreignId('customer_id')->constrained('customers');
            $t->foreignId('source_order_id')->nullable()->constrained('orders');
            $t->timestampTz('ordered_at')->index();
            $t->timestampTz('promised_at')->index();
            $t->timestampTz('original_promised_at')->nullable();
            $t->timestampTz('confirmed_at')->nullable()->index();
            $t->timestampTz('ready_at')->nullable();
            $t->timestampTz('delivered_at')->nullable()->index();
            $t->timestampTz('cancelled_at')->nullable();
            $t->string('stage')->default('New')->index();
            $t->json('contact');
            $t->text('notes')->nullable();
            $t->text('internal_notes')->nullable();
            $t->string('discount_type')->default('fixed');
            $t->bigInteger('discount_value')->default(0);
            $t->json('totals');
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        Schema::create('order_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained('orders');
            $t->foreignId('product_id')->constrained('products');
            $t->foreignId('template_id')->constrained('templates');
            $t->string('description');
            $t->string('category')->nullable();
            $t->unsignedInteger('quantity');
            $t->bigInteger('unit_price');
            $t->bigInteger('offered_price');
            $t->string('price_source');
            $t->string('discount_type')->default('fixed');
            $t->bigInteger('discount_value')->default(0);
            $t->json('taxes');
            $t->json('totals');
            $t->boolean('cost_known')->default(false);
            $t->bigInteger('cost')->default(0);
            $t->string('measurement_mode')->default('shared');
            $t->text('instructions')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        Schema::create('measurement_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_line_id')->constrained('order_lines');
            $t->unsignedInteger('piece')->default(0);
            $t->unsignedInteger('revision')->default(1);
            $t->boolean('current')->default(true);
            $t->foreignId('source_snapshot_id')->nullable()->constrained('measurement_snapshots');
            $t->foreignId('supersedes_id')->nullable()->constrained('measurement_snapshots');
            $t->foreignId('template_id')->constrained('templates');
            $t->string('template_name');
            $t->unsignedInteger('template_revision');
            $t->string('unit');
            $t->json('fields');
            $t->timestampTz('frozen_at')->nullable();
            $t->text('reason')->nullable();
            $t->unique(['order_line_id', 'piece', 'revision']);
            $t->index(['order_line_id', 'current']);
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->unique();
            $t->foreignId('order_id')->unique()->constrained('orders');
            $t->foreignId('customer_id')->constrained('customers');
            $t->string('currency', 3);
            $t->string('state')->default('draft');
            $t->timestampTz('effective_at')->nullable()->index();
            $t->json('contact');
            $t->json('lines');
            $t->json('totals');
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->unique();
            $t->foreignId('customer_id')->constrained('customers');
            $t->foreignId('payment_method_id')->constrained('payment_methods');
            $t->bigInteger('amount');
            $t->timestampTz('effective_at')->index();
            $t->string('external_reference')->nullable();
            $t->string('settlement')->default('settled');
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        Schema::create('settlements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_id')->constrained('payments');
            $t->string('state');
            $t->timestampTz('effective_at')->index();
            $t->text('reason')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        Schema::create('allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_id')->constrained('payments');
            $t->foreignId('invoice_id')->constrained('invoices');
            $t->bigInteger('amount');
            $t->timestampTz('effective_at')->index();
            $t->foreignId('reverses_id')->nullable()->unique()->constrained('allocations');
            $t->text('reason')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        Schema::create('credits', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->unique();
            $t->foreignId('invoice_id')->constrained('invoices');
            $t->foreignId('customer_id')->constrained('customers');
            $t->json('lines');
            $t->bigInteger('amount');
            $t->bigInteger('net');
            $t->bigInteger('tax');
            $t->timestampTz('effective_at')->index();
            $t->text('reason');
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        Schema::create('credit_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('credit_id')->constrained('credits');
            $t->foreignId('invoice_id')->constrained('invoices');
            $t->bigInteger('amount');
            $t->timestampTz('effective_at')->index();
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        Schema::create('refunds', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->unique();
            $t->foreignId('credit_id')->constrained('credits');
            $t->foreignId('payment_method_id')->constrained('payment_methods');
            $t->bigInteger('amount');
            $t->timestampTz('effective_at')->index();
            $t->text('reason');
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        Schema::create('follow_ups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained('customers');
            $t->timestampTz('due_at')->index();
            $t->text('note');
            $t->timestampTz('completed_at')->nullable();
            $t->string('reminder_key')->nullable()->unique();
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users');
            $t->string('entity');
            $t->unsignedBigInteger('entity_id');
            $t->string('action');
            $t->json('changes');
            $t->index(['entity', 'entity_id']);
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        Schema::create('idempotency_records', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->string('hash', 64);
            $t->json('result')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        Schema::create('number_sequences', function (Blueprint $t) {
            $t->id();
            $t->string('kind')->unique();
            $t->unsignedBigInteger('next_number')->default(1);
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX one_owner ON users (is_owner) WHERE is_owner = true');
            DB::statement('CREATE UNIQUE INDEX one_current_measurement ON measurement_snapshots (order_line_id,piece) WHERE current = true');
            DB::statement('ALTER TABLE order_lines ADD CONSTRAINT order_lines_quantity_check CHECK (quantity > 0)');
            DB::statement('ALTER TABLE order_lines ADD CONSTRAINT order_lines_unit_price_check CHECK (unit_price >= 0)');
            DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount > 0)');
            DB::statement('ALTER TABLE refunds ADD CONSTRAINT refunds_amount_check CHECK (amount > 0)');
            DB::statement('ALTER TABLE credits ADD CONSTRAINT credits_amount_check CHECK (amount >= 0)');
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_check CHECK (price >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('idempotency_records');
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('follow_ups');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('credit_allocations');
        Schema::dropIfExists('credits');
        Schema::dropIfExists('allocations');
        Schema::dropIfExists('settlements');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('measurement_snapshots');
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('customer_prices');
        Schema::dropIfExists('products');
        Schema::dropIfExists('templates');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('business_settings');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_owner'));
    }
};
