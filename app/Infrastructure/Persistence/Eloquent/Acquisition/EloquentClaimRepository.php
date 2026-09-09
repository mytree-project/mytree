<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Acquisition;

use App\Application\Acquisition\ClaimNotFound;
use App\Application\Acquisition\ClaimRepository;
use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimCertainty;
use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\ClaimOrigin;
use App\Domain\Acquisition\ClaimQualifiers;
use App\Domain\Acquisition\ClaimValueSerializer;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\SourceId;
use App\Infrastructure\Persistence\Eloquent\Acquisition\Models\ClaimRecord;

final class EloquentClaimRepository implements ClaimRepository
{
    public function add(Claim $claim): void
    {
        ClaimRecord::query()->create($this->attributes($claim, includeOwnership: true));
    }

    public function update(Claim $claim): void
    {
        ClaimRecord::query()
            ->where('id', $claim->id->value)
            ->where('source_id', $claim->sourceId->value)
            ->update($this->attributes($claim, includeOwnership: false));
    }

    public function find(SourceId $sourceId, ClaimId $claimId): ?Claim
    {
        $record = ClaimRecord::query()
            ->where('source_id', $sourceId->value)
            ->where('id', $claimId->value)
            ->first();

        return $record === null ? null : $this->map($record);
    }

    public function forSource(SourceId $sourceId): array
    {
        $claims = [];
        $records = ClaimRecord::query()
            ->where('source_id', $sourceId->value)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($records as $record) {
            $claims[] = $this->map($record);
        }

        return $claims;
    }

    public function remove(SourceId $sourceId, ClaimId $claimId): void
    {
        $deleted = ClaimRecord::query()
            ->where('source_id', $sourceId->value)
            ->where('id', $claimId->value)
            ->delete();

        if ($deleted !== 1) {
            throw ClaimNotFound::forSourceAndId($sourceId, $claimId);
        }
    }

    private function map(ClaimRecord $record): Claim
    {
        $valuePayload = $record->value_payload === null ? null : (string) $record->value_payload;

        return new Claim(
            id: new ClaimId((string) $record->id),
            sourceId: new SourceId((string) $record->source_id),
            subjectMentionId: new MentionId((string) $record->subject_mention_id),
            predicate: PredicateVocabulary::get(
                (string) $record->predicate_key,
                (int) $record->predicate_schema_version,
            ),
            objectMentionId: $record->object_mention_id === null ? null : new MentionId((string) $record->object_mention_id),
            value: $valuePayload === null ? null : ClaimValueSerializer::deserialize($valuePayload),
            qualifiers: ClaimQualifiers::deserialize((string) $record->qualifiers_payload),
            rawText: $record->raw_text === null ? null : (string) $record->raw_text,
            origin: ClaimOrigin::deserialize((string) $record->origin_payload),
            transcriptionCertainty: new ClaimCertainty(
                (string) $record->transcription_certainty,
                (int) $record->transcription_certainty_schema_version,
            ),
            interpretationCertainty: new ClaimCertainty(
                (string) $record->interpretation_certainty,
                (int) $record->interpretation_certainty_schema_version,
            ),
            schemaVersion: (int) $record->schema_version,
        );
    }

    /** @return array<string, mixed> */
    private function attributes(Claim $claim, bool $includeOwnership): array
    {
        $attributes = [
            'schema_version' => $claim->schemaVersion,
            'subject_mention_id' => $claim->subjectMentionId->value,
            'predicate_key' => $claim->predicate->key->value,
            'predicate_schema_version' => $claim->predicate->schemaVersion,
            'object_mention_id' => $claim->objectMentionId?->value,
            'value_payload' => $claim->value === null ? null : ClaimValueSerializer::serialize($claim->value),
            'qualifiers_payload' => $claim->qualifiers->serialize(),
            'raw_text' => $claim->rawText,
            'origin_payload' => $claim->origin->serialize(),
            'transcription_certainty' => $claim->transcriptionCertainty->code,
            'transcription_certainty_schema_version' => $claim->transcriptionCertainty->schemaVersion,
            'interpretation_certainty' => $claim->interpretationCertainty->code,
            'interpretation_certainty_schema_version' => $claim->interpretationCertainty->schemaVersion,
        ];

        if ($includeOwnership) {
            $attributes['id'] = $claim->id->value;
            $attributes['source_id'] = $claim->sourceId->value;
        }

        return $attributes;
    }
}
