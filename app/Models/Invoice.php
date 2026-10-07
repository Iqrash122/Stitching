<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string|int, mixed> $contact
 * @property array<string|int, mixed> $lines
 * @property array<string|int, mixed> $totals
 * @property-read CarbonImmutable|null $effective_at
 * @property-write CarbonInterface|string|null $effective_at
 */
class Invoice extends Model
{
    protected $table = 'invoices';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['contact' => 'array', 'lines' => 'array', 'totals' => 'array', 'effective_at' => 'immutable_datetime'];
    }
}
