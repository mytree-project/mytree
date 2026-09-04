<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Acquisition\Models;

use Illuminate\Database\Eloquent\Model;

final class SourceRecord extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'sources';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'schema_version',
        'source_type_key',
        'source_type_schema_version',
        'metadata',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'schema_version' => 'integer',
            'source_type_schema_version' => 'integer',
            'metadata' => 'array',
        ];
    }
}
