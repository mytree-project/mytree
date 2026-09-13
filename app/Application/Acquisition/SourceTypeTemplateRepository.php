<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

interface SourceTypeTemplateRepository
{
    /** @return list<SourceTypeTemplateVersion> */
    public function latest(): array;

    public function findLatest(SourceTypeTemplateId $templateId): ?SourceTypeTemplateVersion;

    public function findVersion(SourceTypeTemplateId $templateId, int $version): ?SourceTypeTemplateVersion;

    public function create(
        SourceTypeTemplateDefinition $definition,
        ?string $changedBy = null,
    ): SourceTypeTemplateVersion;

    public function append(
        SourceTypeTemplateId $templateId,
        int $expectedVersion,
        SourceTypeTemplateDefinition $definition,
        ?string $changedBy = null,
    ): SourceTypeTemplateVersion;
}
