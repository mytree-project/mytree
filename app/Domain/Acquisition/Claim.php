<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class Claim
{
    public const LEGACY_SCHEMA_VERSION = 1;

    public const PREVIOUS_SCHEMA_VERSION = 2;

    public const SCHEMA_VERSION = 3;

    public ClaimQualifiers $qualifiers;

    public ClaimOrigin $origin;

    public ClaimCertainty $transcriptionCertainty;

    public ClaimCertainty $interpretationCertainty;

    /** @var list<SourceLinguisticRepresentation> */
    public array $sourceLinguisticRepresentations;

    /** @param list<SourceLinguisticRepresentation> $sourceLinguisticRepresentations */
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
        array $sourceLinguisticRepresentations = [],
        public ?ClaimDerivation $derivation = null,
    ) {
        if (! in_array($schemaVersion, [self::LEGACY_SCHEMA_VERSION, self::PREVIOUS_SCHEMA_VERSION, self::SCHEMA_VERSION], true)) {
            throw new InvalidArgumentException('Unsupported Claim schema version.');
        }

        if ($schemaVersion === self::LEGACY_SCHEMA_VERSION && $sourceLinguisticRepresentations !== []) {
            throw new InvalidArgumentException('Legacy Claim schema cannot contain source linguistic representations.');
        }

        $actualOrigin = $origin ?? ClaimOrigin::manualDirectSource();
        if (($actualOrigin->kind === ClaimOriginKind::Derived) !== ($derivation !== null)) {
            throw new InvalidArgumentException('Derived Claim origin requires derivation lineage and direct origin forbids it.');
        }
        if ($derivation !== null && $schemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Derived Claim requires the current Claim schema version.');
        }
        if ($derivation !== null && $sourceLinguisticRepresentations !== []) {
            throw new InvalidArgumentException('Derived Claim cannot own source-supplied linguistic representations.');
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

            if ($sourceLinguisticRepresentations !== []) {
                throw new InvalidArgumentException('Mention-object Predicate Claim cannot contain source linguistic representations.');
            }
        }

        $this->sourceLinguisticRepresentations = SourceLinguisticRepresentationSerializer::normalize(
            $sourceLinguisticRepresentations,
        );
        $this->qualifiers = $qualifiers ?? ClaimQualifiers::empty();
        $this->origin = $actualOrigin;
        $this->transcriptionCertainty = $transcriptionCertainty ?? ClaimCertainty::unspecified();
        $this->interpretationCertainty = $interpretationCertainty ?? ClaimCertainty::unspecified();
    }
}
