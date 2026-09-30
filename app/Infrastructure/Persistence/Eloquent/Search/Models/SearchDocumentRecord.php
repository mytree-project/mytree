<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Search\Models;

use Illuminate\Database\Eloquent\Model;

final class SearchDocumentRecord extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $primaryKey = 'source_id';

    protected $table = 'search_documents';

    /** @var list<string> */
    protected $fillable = [
        'source_id',
        'evidence_state_id',
        'source_revision_id',
        'source_name',
        'source_type_key',
        'input_fingerprint',
        'index_signature',
        'stale',
        'built_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'stale' => 'boolean',
            'built_at' => 'immutable_datetime',
        ];
    }
}
