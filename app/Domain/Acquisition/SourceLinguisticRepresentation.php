<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class SourceLinguisticRepresentation
{
    public const SCHEMA_ID = 'mytree.source-linguistic-representation.v1';

    public const SCHEMA_VERSION = 1;

    /** @var list<SourceLocatorId> */
    public array $sourceLocatorIds;

    /**
     * @param  list<SourceLocatorId>  $sourceLocatorIds
     */
    public function __construct(
        public string $value,
        public ?string $language,
        public ?string $script,
        public SourceLinguisticRepresentationRelation $relation,
        array $sourceLocatorIds = [],
        public int $schemaVersion = self::SCHEMA_VERSION,
    ) {
        if ($schemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported source linguistic representation schema version.');
        }

        if (trim($value) === '') {
            throw new InvalidArgumentException('Source linguistic representation value must not be empty.');
        }

        if ($language !== null && trim($language) === '') {
            throw new InvalidArgumentException('Source linguistic representation language must not be empty when provided.');
        }

        if ($script !== null && trim($script) === '') {
            throw new InvalidArgumentException('Source linguistic representation script must not be empty when provided.');
        }

        $seen = [];
        foreach ($sourceLocatorIds as $sourceLocatorId) {
            if (isset($seen[$sourceLocatorId->value])) {
                throw new InvalidArgumentException('Source linguistic representation locator references must be unique.');
            }

            $seen[$sourceLocatorId->value] = true;
        }

        usort(
            $sourceLocatorIds,
            static fn (SourceLocatorId $left, SourceLocatorId $right): int => strcmp($left->value, $right->value),
        );

        $this->sourceLocatorIds = $sourceLocatorIds;
    }
}
