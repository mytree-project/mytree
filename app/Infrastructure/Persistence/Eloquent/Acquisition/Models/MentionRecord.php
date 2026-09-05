<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Acquisition\Models;

use Illuminate\Database\Eloquent\Model;

final class MentionRecord extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'mentions';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'source_id',
        'schema_version',
        'kind',
        'local_key',
        'role',
        'display_label',
        'raw_data',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'schema_version' => 'integer',
            'raw_data' => 'array',
        ];
    }
}
