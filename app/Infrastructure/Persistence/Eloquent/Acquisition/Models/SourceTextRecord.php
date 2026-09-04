<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Acquisition\Models;

use Illuminate\Database\Eloquent\Model;

final class SourceTextRecord extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'source_texts';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'source_id',
        'schema_version',
        'kind',
        'language',
        'content',
        'position',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'schema_version' => 'integer',
            'position' => 'integer',
        ];
    }
}
