<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

interface SourceLocatorValue
{
    public function type(): SourceLocatorType;

    public function schemaVersion(): int;

    /** @return array<string, mixed> */
    public function data(): array;
}
