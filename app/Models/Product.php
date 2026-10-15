<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string|int, mixed> $taxes
 * @property bool $cost_known
 * @property bool $active
 * @property int $price
 * @property int $cost
 */
class Product extends Model
{
    protected $table = 'products';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['taxes' => 'array', 'cost_known' => 'boolean', 'active' => 'boolean', 'price' => 'integer', 'cost' => 'integer'];
    }

    /** @return BelongsTo<MeasurementTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(MeasurementTemplate::class, 'template_id');
    }
}
