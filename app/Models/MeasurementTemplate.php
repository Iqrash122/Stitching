<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string|int, mixed> $fields
 * @property bool $active
 * @property int $revision
 */
class MeasurementTemplate extends Model
{
    protected $table = 'templates';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['fields' => 'array', 'active' => 'boolean', 'revision' => 'integer'];
    }
}
