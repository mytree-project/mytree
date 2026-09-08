<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Acquisition\Models;

use Illuminate\Database\Eloquent\Model;

final class ClaimRecord extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'claims';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'source_id',
        'schema_version',
        'subject_mention_id',
        'predicate_key',
        'predicate_schema_version',
        'object_mention_id',
        'value_payload',
        'qualifiers_payload',
        'raw_text',
        'origin_payload',
        'transcription_certainty',
        'transcription_certainty_schema_version',
        'interpretation_certainty',
        'interpretation_certainty_schema_version',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'schema_version' => 'integer',
            'predicate_schema_version' => 'integer',
            'transcription_certainty_schema_version' => 'integer',
            'interpretation_certainty_schema_version' => 'integer',
        ];
    }
}
