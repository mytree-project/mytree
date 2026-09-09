<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class Claim
{
    public const SCHEMA_VERSION = 1;

    public ClaimQualifiers $qualifiers;

    public ClaimOrigin $origin;

    public ClaimCertainty $transcriptionCertainty;

    public ClaimCertainty $interpretationCertainty;

    public function __construct(
        public ClaimId $id,
        public SourceId $sourceId,
        public MentionId $subjectMentionId,
        public Predicate $predicate,
        public ?MentionId $objectMentionId = null,
        public ?ClaimValue $value = null,
        ?ClaimQualifiers $qualifiers = null,
        public ?string $rawText = null,
        ?ClaimOrigin $origin = null,
        ?ClaimCertainty $transcriptionCertainty = null,
        ?ClaimCertainty $interpretationCertainty = null,
        public int $schemaVersion = self::SCHEMA_VERSION,
    ) {
        if ($schemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported Claim schema version.');
        }

        if ($rawText !== null && trim($rawText) === '') {
            throw new InvalidArgumentException('Claim raw text must not be empty when provided.');
        }

        if ($predicate->literalValueType !== null) {
            if ($objectMentionId !== null || $value === null) {
                throw new InvalidArgumentException('Literal Predicate Claim requires a value and forbids an object Mention.');
            }

            $predicate->assertLiteralValue($value);
        } else {
            if ($objectMentionId === null || $value !== null) {
                throw new InvalidArgumentException('Mention-object Predicate Claim requires an object Mention and forbids a literal value.');
            }
        }

        $this->qualifiers = $qualifiers ?? ClaimQualifiers::empty();
        $this->origin = $origin ?? ClaimOrigin::manualDirectSource();
        $this->transcriptionCertainty = $transcriptionCertainty ?? ClaimCertainty::unspecified();
        $this->interpretationCertainty = $interpretationCertainty ?? ClaimCertainty::unspecified();
    }
}
