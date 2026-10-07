<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property bool $active
 */
class PaymentMethod extends Model
{
    protected $table = 'payment_methods';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
