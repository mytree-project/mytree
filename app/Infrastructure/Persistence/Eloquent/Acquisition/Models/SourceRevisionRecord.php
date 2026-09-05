<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Acquisition\Models;

use Illuminate\Database\Eloquent\Model;

final class SourceRevisionRecord extends Model
{
    public $timestamps = false;

    protected $table = 'source_revisions';

    /** @var list<string> */
    protected $fillable = [
        'source_id',
        'revision_number',
        'snapshot_schema_version',
        'canonical_payload',
        'payload_hash',
        'recorded_at',
        'change_note',
        'changed_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'snapshot_schema_version' => 'integer',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
