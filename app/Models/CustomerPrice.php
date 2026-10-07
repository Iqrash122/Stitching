<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property bool $active
 * @property int $price
 */
class CustomerPrice extends Model
{
    protected $table = 'customer_prices';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['active' => 'boolean', 'price' => 'integer'];
    }
}
