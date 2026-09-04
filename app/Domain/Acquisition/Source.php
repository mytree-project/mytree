<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class Source
{
    public const SCHEMA_VERSION = 1;

    /** @var list<SourceText> */
    public array $texts;

    /**
     * @param list<SourceText> $texts
     */
    public function __construct(
        public SourceId $id,
        public SourceType $type,
        public SourceMetadata $metadata,
        array $texts = [],
        public int $schemaVersion = self::SCHEMA_VERSION,
    ) {
        if ($schemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported Source schema version.');
        }

        $seenTextIds = [];

        foreach ($texts as $text) {
            if (isset($seenTextIds[$text->id->value])) {
                throw new InvalidArgumentException('Source text ids must be unique within a Source.');
            }

            $seenTextIds[$text->id->value] = true;
        }

        $this->texts = array_values($texts);
    }
}
