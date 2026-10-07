<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string|int, mixed> $contact
 * @property array<string|int, mixed> $method_snapshot
 * @property-read CarbonImmutable|null $effective_at
 * @property-write CarbonInterface|string|null $effective_at
 * @property int $amount
 */
class Payment extends Model
{
    protected $table = 'payments';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['contact' => 'array', 'method_snapshot' => 'array', 'effective_at' => 'immutable_datetime', 'amount' => 'integer'];
    }
}
