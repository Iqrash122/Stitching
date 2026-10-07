<?php

namespace App\Domain;

use App\Models\AuditEvent;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\CustomerPrice;
use App\Models\Invoice;
use App\Models\MeasurementSnapshot;
use App\Models\MeasurementTemplate;
use App\Models\OrderLine;
use App\Models\Product;
use App\Models\StitchingOrder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class Orders
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public static function audit(Model $model, string $action, array $changes = []): void
    {
        AuditEvent::create(['user_id' => auth()->id(), 'entity' => $model->getTable(), 'entity_id' => $model->getKey(), 'action' => $action, 'changes' => $changes]);
    }

    public static function number(string $kind): string
    {
        $s = DB::table('number_sequences')->where('kind', $kind)->lockForUpdate()->first();
        if (! $s) {
            Pricing::fail('Run the application seeders first.');
        }DB::table('number_sequences')->where('id', $s->id)->increment('next_number');

        return $kind.'-'.str_pad((string) $s->next_number, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $class
     * @return T
     */
    public static function lock(string $class, int $id, ?int $version = null): Model
    {
        $m = (new $class)->newQuery()->lockForUpdate()->findOrFail($id);
        if ($version !== null && $m->getAttribute('version') !== $version) {
            Pricing::fail('This record changed in another window. Refresh and try again.');
        }

        if (! $m instanceof $class) {
            throw new \LogicException('Unexpected locked model type.');
        }

        return $m;
    }

    public static function touch(Model $m): void
    {
        $m->setAttribute('version', $m->getAttribute('version') + 1);
        $m->save();
    }

    public static function date(string $date): Carbon
    {
        try {
            return Carbon::parse($date, BusinessSetting::findOrFail(1)->timezone)->utc();
        } catch (\Throwable) {
            Pricing::fail('Enter a valid date and time.');
        }
    }

    public static function active(StitchingOrder $o): void
    {
        if (in_array($o->stage, ['Delivered', 'Cancelled'])) {
            Pricing::fail('Delivered and cancelled orders cannot be changed.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function create(array $data): StitchingOrder
    {
        return DB::transaction(function () use ($data) {
            $c = Customer::where('active', true)->where('id', $data['customer_id'])->firstOrFail();
            $o = StitchingOrder::create(['reference' => self::number('ST'), 'customer_id' => $c->id, 'source_order_id' => $data['source_order_id'] ?? null, 'ordered_at' => now(), 'promised_at' => self::date($data['promised_at']), 'contact' => $c->only(['name', 'phone', 'whatsapp', 'address']), 'notes' => $data['notes'] ?? '', 'internal_notes' => $data['internal_notes'] ?? '', 'discount_type' => $data['discount_type'] ?? 'fixed', 'discount_value' => Pricing::minor($data['discount_value'] ?? '0'), 'totals' => []]);
            self::garments($o, $c, $data['lines']);
            self::recalculate($o);
            self::audit($o, 'created');

            return $o;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private static function garments(StitchingOrder $o, Customer $c, array $lines): void
    {
        if (! $lines || count($lines) > 100) {
            Pricing::fail('An order must have between one and 100 garment lines.');
        }
        foreach ($lines as $input) {
            if (! is_int($input['quantity']) && ! ctype_digit((string) $input['quantity'])) {
                Pricing::fail('Quantity must be a whole number.');
            }
            if ((int) $input['quantity'] < 1 || (int) $input['quantity'] > 1000 || ! in_array($input['measurement_mode'] ?? 'shared', ['shared', 'per_piece'])) {
                Pricing::fail('Invalid quantity or measurement mode.');
            }
            $p = Product::where('active', true)->where('id', $input['product_id'])->firstOrFail();
            $rule = CustomerPrice::where(['customer_id' => $c->id, 'product_id' => $p->id, 'active' => true])->first();
            $offered = $rule ? $rule->price : $p->price;
            $manual = isset($input['price']) && $input['price'] !== '';
            $line = OrderLine::create(['order_id' => $o->id, 'product_id' => $p->id, 'template_id' => $p->template_id, 'description' => $p->name, 'category' => $p->category, 'quantity' => $input['quantity'], 'unit_price' => $manual ? Pricing::minor($input['price']) : $offered, 'offered_price' => $offered, 'price_source' => $manual ? 'manual' : ($rule ? 'customer' : 'product'), 'discount_type' => $input['discount_type'] ?? 'fixed', 'discount_value' => Pricing::minor($input['discount_value'] ?? '0'), 'taxes' => $p->taxes, 'totals' => [], 'cost_known' => $p->cost_known, 'cost' => $p->cost, 'measurement_mode' => $input['measurement_mode'] ?? 'shared', 'instructions' => $input['instructions'] ?? '']);
            $t = MeasurementTemplate::where('active', true)->findOrFail($p->template_id);
            foreach ($line->measurement_mode === 'shared' ? [0] : range(1, $line->quantity) as $piece) {
                $fields = array_map(fn ($f) => [...$f, 'value' => ! empty($f['checkbox']) ? 'no' : null, 'is_set' => ! empty($f['checkbox'])], $t->fields);
                MeasurementSnapshot::create(['order_line_id' => $line->id, 'piece' => $piece, 'template_id' => $t->id, 'template_name' => $t->name, 'template_revision' => $t->revision, 'unit' => $t->unit, 'fields' => $fields]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function editDraft(int $id, array $data): StitchingOrder
    {
        return DB::transaction(function () use ($id, $data) {
            $o = self::lock(StitchingOrder::class, $id, $data['version']);
            self::active($o);
            if ($o->confirmed_at) {
                Pricing::fail('Confirmed commercial details cannot be edited. Create a linked correction order.');
            }
            if (empty($data['reset_measurements'])) {
                Pricing::fail('Confirm the measurement reset before changing draft garments.');
            }
            $c = Customer::where('active', true)->where('id', $data['customer_id'])->firstOrFail();
            MeasurementSnapshot::whereIn('order_line_id', $o->lines()->select('id'))->delete();
            $o->lines()->delete();
            $o->setAttribute('customer_id', $c->id);
            $o->contact = $c->only(['name', 'phone', 'whatsapp', 'address']);
            $o->promised_at = self::date($data['promised_at']);
            $o->notes = $data['notes'] ?? '';
            $o->internal_notes = $data['internal_notes'] ?? '';
            $o->discount_type = $data['discount_type'];
            $o->discount_value = Pricing::minor($data['discount_value']);
            self::garments($o, $c, $data['lines']);
            self::recalculate($o);
            self::touch($o);
            self::audit($o, 'draft_reset_and_edited');

            return $o;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function copyToPieces(int $id, int $snapshotId, array $data): void
    {
        DB::transaction(function () use ($id, $snapshotId, $data) {
            $o = self::lock(StitchingOrder::class, $id, $data['version']);
            self::active($o);
            $s = MeasurementSnapshot::whereIn('order_line_id', $o->lines()->select('id'))->where('current', true)->findOrFail($snapshotId);
            $values = array_column($s->fields, 'value', 'code');
            foreach (MeasurementSnapshot::where('order_line_id', $s->order_line_id)->where('current', true)->where('id', '!=', $s->id)->get() as $target) {
                self::saveSnapshot($id, $target->id, ['version' => $o->fresh()->version, 'values' => $values, 'reason' => $data['reason'] ?? '']);
            }
        });
    }

    public static function recalculate(StitchingOrder $o): void
    {
        $lines = $o->lines()->get();
        $calc = Pricing::calculate($lines->toArray(), $o->discount_type, $o->discount_value);
        foreach ($lines as $i => $l) {
            $l->totals = $calc['lines'][$i];
            $l->save();
        }$o->totals = $calc;
        $o->save();
    }

    public static function complete(MeasurementSnapshot $s): bool
    {
        foreach ($s->fields as $f) {
            if (($f['required'] ?? false) && ! ($f['is_set'] ?? false)) {
                return false;
            }
        }

        return true;
    }

    public static function coverage(StitchingOrder $o): void
    {
        if (! $o->lines()->exists()) {
            Pricing::fail('Add at least one garment.');
        }
        foreach ($o->lines()->get() as $l) {
            $snap = $l->snapshots()->where('current', true)->orderBy('piece')->get();
            $expected = $l->measurement_mode === 'shared' ? [0] : range(1, $l->quantity);
            if ($snap->pluck('piece')->all() !== $expected) {
                Pricing::fail('Every garment piece needs its own measurement snapshot.');
            }foreach ($snap as $s) {
                if (! self::complete($s)) {
                    Pricing::fail('Complete all required measurements before continuing.');
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function saveSnapshot(int $orderId, int $snapshotId, array $data): void
    {
        DB::transaction(function () use ($orderId, $snapshotId, $data) {
            $o = self::lock(StitchingOrder::class, $orderId, $data['version']);
            self::active($o);
            $s = MeasurementSnapshot::whereIn('order_line_id', $o->lines()->select('id'))->where('current', true)->findOrFail($snapshotId);
            if ($o->confirmed_at && empty(trim($data['reason'] ?? ''))) {
                Pricing::fail('A reason is required for confirmed measurement corrections.');
            }
            $values = $data['values'];
            $fields = [];
            foreach ($s->fields as $f) {
                $v = $values[$f['code']] ?? null;
                $set = $v !== null && $v !== '';
                if ($set && $f['type'] === 'numeric') {
                    if (! preg_match('/^-?\d{1,8}(\.\d{1,4})?$/', (string) $v)) {
                        Pricing::fail($f['label'].' must be numeric.');
                    }if (isset($f['min']) && bccomp(Pricing::decimal($v), Pricing::decimal($f['min']), 4) < 0) {
                        Pricing::fail($f['label'].' is below the minimum.');
                    }if (isset($f['max']) && bccomp(Pricing::decimal($v), Pricing::decimal($f['max']), 4) > 0) {
                        Pricing::fail($f['label'].' is above the maximum.');
                    }
                }
                if ($set && $f['type'] === 'choice' && ! in_array($v, array_column($f['options'], 'code'), true)) {
                    Pricing::fail('Invalid choice for '.$f['label']);
                }
                if ($set && mb_strlen((string) $v) > 2000) {
                    Pricing::fail('Measurement text is too long.');
                }$fields[] = [...$f, 'value' => $set ? (string) $v : null, 'is_set' => $set];
            }
            $source = null;
            if (! empty($data['source_snapshot_id'])) {
                $source = self::previous($o->customer_id, $s->template_id)->firstWhere('id', (int) $data['source_snapshot_id']);
                if (! $source || $source->unit !== $s->unit) {
                    Pricing::fail('The source measurements or units are not compatible.');
                }
            }
            if ($o->confirmed_at) {
                $s->current = false;
                $s->save();
                $new = $s->replicate();
                $new->current = true;
                $new->revision++;
                $new->setAttribute('supersedes_id', $s->id);
                $new->reason = $data['reason'];
                $new->frozen_at = now();
                $new->fields = $fields;
                $new->setAttribute('source_snapshot_id', $source?->id);
                $new->save();
                self::audit($o, 'measurements_revised', ['previous' => $s->id, 'revision' => $new->id, 'reason' => $data['reason']]);
            } else {
                $s->fields = $fields;
                $s->setAttribute('source_snapshot_id', $source?->id);
                $s->save();
                self::audit($o, 'measurements_saved', ['snapshot' => $s->id]);
            }
            self::touch($o);
        });
    }

    /** @return Collection<int, MeasurementSnapshot> */
    public static function previous(int $customer, int $template, ?int $excludeOrderId = null): Collection
    {
        return MeasurementSnapshot::query()->select('measurement_snapshots.*', 'orders.reference as source_order', 'orders.confirmed_at as source_date')
            ->join('order_lines', 'order_lines.id', '=', 'measurement_snapshots.order_line_id')->join('orders', 'orders.id', '=', 'order_lines.order_id')
            ->where('measurement_snapshots.template_id', $template)->whereNotNull('measurement_snapshots.frozen_at')->where('measurement_snapshots.current', true)
            ->where('orders.customer_id', $customer)->whereNotNull('orders.confirmed_at')->where('orders.stage', '!=', 'Cancelled')
            ->when($excludeOrderId, fn ($q) => $q->where('orders.id', '!=', $excludeOrderId))
            ->orderByRaw('CASE WHEN orders.delivered_at IS NULL THEN 1 ELSE 0 END')->orderByDesc('orders.delivered_at')->orderByDesc('orders.confirmed_at')->orderByDesc('measurement_snapshots.id')->limit(1)->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function transition(int $id, array $data): void
    {
        DB::transaction(function () use ($id, $data) {
            $o = self::lock(StitchingOrder::class, $id, $data['version']);
            self::active($o);
            $action = $data['action'];
            $changes = ['from' => $o->stage];
            if ($action === 'confirm') {
                if ($o->confirmed_at) {
                    Pricing::fail('Order is already confirmed.');
                }self::coverage($o);
                self::recalculate($o);
                $o->confirmed_at = now();
                $o->original_promised_at = $o->promised_at;
                MeasurementSnapshot::whereIn('order_line_id', $o->lines()->select('id'))->where('current', true)->update(['frozen_at' => now()]);
            } elseif ($action === 'reschedule') {
                if (! $o->confirmed_at || empty(trim($data['reason'] ?? ''))) {
                    Pricing::fail('A confirmed order and reason are required.');
                }$changes = ['old' => $o->promised_at, 'new' => $data['promised_at'], 'reason' => $data['reason']];
                $o->promised_at = self::date($data['promised_at']);
            } elseif ($action === 'cancel') {
                if (empty(trim($data['reason'] ?? ''))) {
                    Pricing::fail('Enter a cancellation reason.');
                }$o->stage = 'Cancelled';
                $o->cancelled_at = now();
                $changes['reason'] = $data['reason'];
                $changes['billing_exception'] = 'Invoices and debts are preserved. Review credits/refunds separately.';
            } else {
                $allowed = ['New' => 'Cutting', 'Cutting' => 'Stitching', 'Stitching' => 'Ready', 'Ready' => 'Delivered'];
                if (! $o->confirmed_at || ($allowed[$o->stage] ?? null) !== $action) {
                    Pricing::fail('This production transition is not allowed.');
                }self::coverage($o);
                if ($action === 'Delivered') {
                    $invoice = Invoice::where('order_id', $o->id)->lockForUpdate()->first();
                    if (! $invoice || $invoice->state !== 'posted') {
                        Pricing::fail('Post the primary invoice before delivery.');
                    }$due = Billing::balance($invoice);
                    if ($due > 0 && (! BusinessSetting::findOrFail(1)->credit_delivery || empty(trim($data['reason'] ?? '')))) {
                        Pricing::fail('Collect the outstanding balance, or enable delivery on credit and enter a reason.');
                    }$o->delivered_at = now();
                    $changes['credit_reason'] = $data['reason'] ?? null;
                    $changes['due'] = $due;
                }
                if ($action === 'Ready') {
                    $o->ready_at = now();
                }$o->stage = $action;
            } self::touch($o);
            self::audit($o, $action, $changes);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function repeat(int $id, array $data): StitchingOrder
    {
        return DB::transaction(function () use ($id, $data) {
            $old = StitchingOrder::with('lines.snapshots')->findOrFail($id);
            if (! $old->confirmed_at || $old->stage === 'Cancelled') {
                Pricing::fail('Repeat a confirmed, non-cancelled order.');
            }$lines = [];
            foreach ($old->lines as $l) {
                $lines[] = ['product_id' => $l->product_id, 'quantity' => $l->quantity, 'price' => $data['pricing'] === 'previous' ? bcdiv((string) $l->unit_price, '100', 2) : '', 'measurement_mode' => $l->measurement_mode, 'instructions' => $l->instructions, 'discount_type' => $l->discount_type, 'discount_value' => bcdiv((string) $l->discount_value, '100', 2)];
            }
            $new = self::create(['customer_id' => $old->customer_id, 'source_order_id' => $old->id, 'promised_at' => $data['promised_at'], 'lines' => $lines, 'discount_type' => $old->discount_type, 'discount_value' => bcdiv((string) $old->discount_value, '100', 2), 'notes' => $old->notes]);
            foreach ($new->lines()->get() as $i => $l) {
                foreach ($l->snapshots()->get() as $s) {
                    $src = $old->lines[$i]->snapshots->where('current', true)->firstWhere('piece', $s->piece);
                    if ($src && $src->unit === $s->unit) {
                        $mapped = collect($src->fields)->keyBy('code');
                        $s->fields = array_map(function ($f) use ($mapped) {
                            $v = $mapped->get($f['code']);

                            return $v && $v['type'] === $f['type'] && $v['unit'] === $f['unit'] && ($f['type'] !== 'choice' || in_array($v['value'], array_column($f['options'], 'code'))) ? [...$f, 'value' => $v['value'], 'is_set' => $v['is_set']] : $f;
                        }, $s->fields);
                        $s->setAttribute('source_snapshot_id', $src->id);
                        $s->save();
                    }
                }
            }self::audit($new, 'repeated', ['source' => $old->id, 'pricing' => $data['pricing']]);

            return $new;
        });
    }
}
