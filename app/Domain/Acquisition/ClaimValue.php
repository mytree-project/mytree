<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

interface ClaimValue
{
    public function type(): ClaimValueType;

    public function schemaVersion(): int;

    public function raw(): string;

    /** @return array<string, mixed> */
    public function data(): array;
}
