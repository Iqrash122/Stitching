<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read CarbonImmutable|null $due_at
 * @property-write CarbonInterface|string|null $due_at
 * @property-read CarbonImmutable|null $completed_at
 * @property-write CarbonInterface|string|null $completed_at
 */
class FollowUp extends Model
{
    protected $table = 'follow_ups';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['due_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }
}
