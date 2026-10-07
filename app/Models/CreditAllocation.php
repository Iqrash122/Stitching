<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read CarbonImmutable|null $effective_at
 * @property-write CarbonInterface|string|null $effective_at
 * @property int $amount
 */
class CreditAllocation extends Model
{
    protected $table = 'credit_allocations';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['effective_at' => 'immutable_datetime', 'amount' => 'integer'];
    }
}
