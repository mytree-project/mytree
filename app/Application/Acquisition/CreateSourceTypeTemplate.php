<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

final readonly class CreateSourceTypeTemplate
{
    public function __construct(
        private SourceTypeTemplateRepository $repository,
        private ValidateSourceTypeTemplateDefinition $validate,
    ) {}

    public function handle(
        SourceTypeTemplateDefinition $definition,
        ?string $changedBy = null,
    ): SourceTypeTemplateVersion {
        $this->validate->handle($definition);

        return $this->repository->create($definition, $changedBy);
    }
}
