<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class Source
{
    public const SCHEMA_VERSION = 1;

    public const MAX_NAME_LENGTH = 255;

    /** @var list<SourceText> */
    public array $texts;

    public ?string $name;

    /** @param list<SourceText> $texts */
    public function __construct(
        public SourceId $id,
        public SourceType $type,
        public SourceMetadata $metadata,
        array $texts = [],
        public int $schemaVersion = self::SCHEMA_VERSION,
        ?string $name = null,
    ) {
        if ($schemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported Source schema version.');
        }

        $name = $name === null ? null : trim($name);
        if ($name === '') {
            $name = null;
        }
        if ($name !== null && mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'Source name must not exceed %d characters.',
                self::MAX_NAME_LENGTH,
            ));
        }
        $this->name = $name;

        $seenTextIds = [];

        foreach ($texts as $text) {
            if (isset($seenTextIds[$text->id->value])) {
                throw new InvalidArgumentException('Source text ids must be unique within a Source.');
            }

            $seenTextIds[$text->id->value] = true;
        }

        $this->texts = $texts;
    }
}
