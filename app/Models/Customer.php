<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property bool $active
 */
class Customer extends Model
{
    protected $table = 'customers';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** @return HasMany<StitchingOrder, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(StitchingOrder::class, 'customer_id');
    }
}
