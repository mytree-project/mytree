<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class ImageBoundingBoxLocatorValue implements SourceLocatorValue
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public string $coordinateSpace = 'pixels',
        public int $valueSchemaVersion = self::SCHEMA_VERSION,
    ) {
        if ($valueSchemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported image bounding-box locator value schema version.');
        }

        if ($x < 0 || $y < 0 || $width <= 0 || $height <= 0) {
            throw new InvalidArgumentException('Image bounding-box coordinates must have non-negative origin and positive size.');
        }

        if (trim($coordinateSpace) === '' || strlen($coordinateSpace) > 64) {
            throw new InvalidArgumentException('Image bounding-box coordinate space must be non-empty and at most 64 bytes.');
        }
    }

    public function type(): SourceLocatorType
    {
        return SourceLocatorType::ImageBoundingBox;
    }

    public function schemaVersion(): int
    {
        return $this->valueSchemaVersion;
    }

    public function data(): array
    {
        return [
            'coordinate_space' => $this->coordinateSpace,
            'height' => $this->height,
            'width' => $this->width,
            'x' => $this->x,
            'y' => $this->y,
        ];
    }
}
