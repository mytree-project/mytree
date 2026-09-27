<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Search\Models;

use Illuminate\Database\Eloquent\Model;

final class SearchDocumentEntryRecord extends Model
{
    public $timestamps = false;

    protected $table = 'search_document_entries';

    /** @var list<string> */
    protected $fillable = [
        'source_id',
        'field',
        'value',
        'origin',
        'mention_id',
        'claim_id',
        'claim_revision_id',
        'language',
        'script',
        'representation_relation',
        'index_forms',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'index_forms' => 'array',
        ];
    }
}
