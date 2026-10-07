<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property array<string|int, mixed> $contact
 * @property array<string|int, mixed> $totals
 * @property-read CarbonImmutable|null $ordered_at
 * @property-write CarbonInterface|string|null $ordered_at
 * @property-read CarbonImmutable|null $promised_at
 * @property-write CarbonInterface|string|null $promised_at
 * @property-read CarbonImmutable|null $original_promised_at
 * @property-write CarbonInterface|string|null $original_promised_at
 * @property-read CarbonImmutable|null $confirmed_at
 * @property-write CarbonInterface|string|null $confirmed_at
 * @property-read CarbonImmutable|null $ready_at
 * @property-write CarbonInterface|string|null $ready_at
 * @property-read CarbonImmutable|null $delivered_at
 * @property-write CarbonInterface|string|null $delivered_at
 * @property-read CarbonImmutable|null $cancelled_at
 * @property-write CarbonInterface|string|null $cancelled_at
 * @property int $discount_value
 */
class StitchingOrder extends Model
{
    protected $table = 'orders';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['contact' => 'array', 'totals' => 'array', 'ordered_at' => 'immutable_datetime', 'promised_at' => 'immutable_datetime', 'original_promised_at' => 'immutable_datetime', 'confirmed_at' => 'immutable_datetime', 'ready_at' => 'immutable_datetime', 'delivered_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime', 'discount_value' => 'integer'];
    }

    /** @return HasMany<OrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class, 'order_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasOne<Invoice, $this> */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'order_id');
    }
}
