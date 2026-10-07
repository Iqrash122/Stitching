<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string|int, mixed> $lines
 * @property-read CarbonImmutable|null $effective_at
 * @property-write CarbonInterface|string|null $effective_at
 * @property int $amount
 * @property int $net
 * @property int $tax
 */
class Credit extends Model
{
    protected $table = 'credits';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['lines' => 'array', 'effective_at' => 'immutable_datetime', 'amount' => 'integer', 'net' => 'integer', 'tax' => 'integer'];
    }
}
