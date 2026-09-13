<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use InvalidArgumentException;

final readonly class SourceTypeTemplateId
{
    public string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower($value);

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $normalized) !== 1) {
            throw new InvalidArgumentException('Source Type Template id must be a UUID.');
        }

        $this->value = $normalized;
    }
}
