<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Source;
use App\Domain\Acquisition\SourceId;

final readonly class GetSource
{
    public function __construct(
        private SourceRepository $repository,
    ) {}

    public function handle(SourceId $id): Source
    {
        return $this->repository->find($id) ?? throw SourceNotFound::forId($id);
    }
}
