<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
