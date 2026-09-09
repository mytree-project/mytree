<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Acquisition\Models;

use Illuminate\Database\Eloquent\Model;

final class ClaimRevisionRecord extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $table = 'claim_revisions';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'source_id',
        'claim_id',
        'revision_number',
        'subject_mention_revision_id',
        'object_mention_revision_id',
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
