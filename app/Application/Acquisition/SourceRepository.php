<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Source;
use App\Domain\Acquisition\SourceId;

interface SourceRepository
{
    public function save(Source $source): void;

    public function find(SourceId $id): ?Source;
}
