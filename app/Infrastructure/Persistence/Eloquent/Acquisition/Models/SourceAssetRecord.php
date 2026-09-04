<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Acquisition\Models;

use Illuminate\Database\Eloquent\Model;

final class SourceAssetRecord extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'source_assets';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'source_id',
        'schema_version',
        'storage_disk',
        'storage_path',
        'original_filename',
        'mime_type',
        'byte_size',
        'sha256',
        'metadata',
        'provenance',
        'retrieved_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'schema_version' => 'integer',
            'byte_size' => 'integer',
            'metadata' => 'array',
            'provenance' => 'array',
            'retrieved_at' => 'immutable_datetime',
        ];
    }
}
