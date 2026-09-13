<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Acquisition\Models;

use Illuminate\Database\Eloquent\Model;

final class SourceTypeTemplateVersionRecord extends Model
{
    protected $table = 'source_type_template_versions';

    /** @var list<string> */
    protected $fillable = [
        'template_id',
        'version',
        'name',
        'description',
        'compatible_source_types',
        'default_field_keys',
        'is_active',
        'changed_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'compatible_source_types' => 'array',
            'default_field_keys' => 'array',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
