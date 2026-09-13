<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

final readonly class GetSourceTypeTemplateVersion
{
    public function __construct(private SourceTypeTemplateRepository $repository) {}

    public function handle(SourceTypeTemplateId $templateId, int $version): SourceTypeTemplateVersion
    {
        return $this->repository->findVersion($templateId, $version)
            ?? throw new SourceTypeTemplateNotFound($templateId, $version);
    }
}
