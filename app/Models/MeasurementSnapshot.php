<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string|int, mixed> $fields
 * @property bool $current
 * @property-read CarbonImmutable|null $frozen_at
 * @property-write CarbonInterface|string|null $frozen_at
 * @property int $piece
 * @property int $revision
 * @property int $template_revision
 */
class MeasurementSnapshot extends Model
{
    protected $table = 'measurement_snapshots';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['fields' => 'array', 'current' => 'boolean', 'frozen_at' => 'immutable_datetime', 'piece' => 'integer', 'revision' => 'integer', 'template_revision' => 'integer'];
    }
}
