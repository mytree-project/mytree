<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Acquisition\Models;

use Illuminate\Database\Eloquent\Model;

final class DerivedClaimProposalRecord extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'derived_claim_proposals';

    /** @var list<string> */
    protected $fillable = [
        'id', 'source_id', 'subject_mention_id', 'predicate_key',
        'predicate_schema_version', 'object_mention_id', 'value_payload',
        'qualifiers_payload', 'derivation_payload', 'rule_id', 'output_key',
        'fingerprint', 'status', 'accepted_claim_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['predicate_schema_version' => 'integer'];
    }
}
