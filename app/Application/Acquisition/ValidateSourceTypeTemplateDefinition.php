<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use InvalidArgumentException;

final readonly class ValidateSourceTypeTemplateDefinition
{
    public function __construct(private SupportedAcquisitionFieldCatalog $fields) {}

    public function handle(SourceTypeTemplateDefinition $definition): void
    {
        foreach ($definition->defaultFieldKeys as $fieldKey) {
            if (! $this->fields->has($fieldKey)) {
                throw new InvalidArgumentException(sprintf(
                    'Source Type Template references unsupported acquisition field "%s".',
                    $fieldKey,
                ));
            }
        }
    }
}
