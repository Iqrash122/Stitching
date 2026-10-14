<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string|int, mixed> $taxes
 * @property array<string|int, mixed> $totals
 * @property bool $cost_known
 * @property int $quantity
 * @property int $unit_price
 * @property int $offered_price
 * @property int $discount_value
 * @property int $cost
 */
class OrderLine extends Model
{
    protected $table = 'order_lines';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['taxes' => 'array', 'totals' => 'array', 'cost_known' => 'boolean', 'quantity' => 'integer', 'unit_price' => 'integer', 'offered_price' => 'integer', 'discount_value' => 'integer', 'cost' => 'integer'];
    }

    /** @return HasMany<MeasurementSnapshot, $this> */
    public function snapshots(): HasMany
    {
        return $this->hasMany(MeasurementSnapshot::class, 'order_line_id');
    }

    /** @return BelongsTo<StitchingOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(StitchingOrder::class, 'order_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /** @return BelongsTo<MeasurementTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(MeasurementTemplate::class, 'template_id');
    }
}
