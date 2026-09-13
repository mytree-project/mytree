<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

final readonly class UpdateSourceTypeTemplate
{
    public function __construct(
        private SourceTypeTemplateRepository $repository,
        private ValidateSourceTypeTemplateDefinition $validate,
    ) {}

    public function handle(
        SourceTypeTemplateId $templateId,
        int $expectedVersion,
        SourceTypeTemplateDefinition $definition,
        ?string $changedBy = null,
    ): SourceTypeTemplateVersion {
        $current = $this->repository->findLatest($templateId)
            ?? throw new SourceTypeTemplateNotFound($templateId);

        if ($current->version !== $expectedVersion) {
            throw new SourceTypeTemplateConflict(
                templateId: $templateId,
                expectedVersion: $expectedVersion,
                currentVersion: $current->version,
            );
        }

        $this->validate->handle($definition);

        if ($definition->sameConfigurationAs($current->definition)) {
            return $current;
        }

        return $this->repository->append($templateId, $expectedVersion, $definition, $changedBy);
    }
}
