<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\SourceType;

final readonly class ListSourceTypeTemplates
{
    public function __construct(private SourceTypeTemplateRepository $repository) {}

    /** @return list<SourceTypeTemplateVersion> */
    public function handle(
        bool $activeOnly = false,
        ?SourceType $compatibleSourceType = null,
    ): array {
        $templates = array_values(array_filter(
            $this->repository->latest(),
            static function (SourceTypeTemplateVersion $template) use ($activeOnly, $compatibleSourceType): bool {
                if ($activeOnly && ! $template->definition->active) {
                    return false;
                }

                return $compatibleSourceType === null
                    || $template->definition->isCompatibleWith($compatibleSourceType);
            },
        ));

        usort(
            $templates,
            static fn (SourceTypeTemplateVersion $left, SourceTypeTemplateVersion $right): int => [
                mb_strtolower($left->definition->name),
                $left->templateId->value,
            ] <=> [
                mb_strtolower($right->definition->name),
                $right->templateId->value,
            ],
        );

        return $templates;
    }
}
