<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\SourceType;
use InvalidArgumentException;

final readonly class SourceTypeTemplateDefinition
{
    public string $name;

    public ?string $description;

    /** @var list<SourceType> */
    public array $compatibleSourceTypes;

    /** @var list<string> */
    public array $defaultFieldKeys;

    /**
     * @param  list<SourceType>  $compatibleSourceTypes
     * @param  list<string>      $defaultFieldKeys
     */
    public function __construct(
        string $name,
        ?string $description = null,
        array $compatibleSourceTypes = [],
        array $defaultFieldKeys = [],
        public bool $active = true,
    ) {
        $normalizedName = trim($name);
        if ($normalizedName === '') {
            throw new InvalidArgumentException('Source Type Template name must not be empty.');
        }

        $normalizedDescription = $description === null ? null : trim($description);
        if ($normalizedDescription === '') {
            $normalizedDescription = null;
        }

        $sourceTypeKeys = [];
        foreach ($compatibleSourceTypes as $sourceType) {
            $identity = $sourceType->key.'@'.$sourceType->schemaVersion;
            if (isset($sourceTypeKeys[$identity])) {
                throw new InvalidArgumentException(sprintf(
                    'Compatible Source type "%s" schema v%d is duplicated.',
                    $sourceType->key,
                    $sourceType->schemaVersion,
                ));
            }

            $sourceTypeKeys[$identity] = true;
        }

        usort(
            $compatibleSourceTypes,
            static fn (SourceType $left, SourceType $right): int => [$left->key, $left->schemaVersion] <=> [$right->key, $right->schemaVersion],
        );

        $fieldKeys = [];
        foreach ($defaultFieldKeys as $fieldKey) {
            if ($fieldKey === '') {
                throw new InvalidArgumentException('Source Type Template field key must not be empty.');
            }

            if (isset($fieldKeys[$fieldKey])) {
                throw new InvalidArgumentException(sprintf(
                    'Source Type Template field "%s" is duplicated.',
                    $fieldKey,
                ));
            }

            $fieldKeys[$fieldKey] = true;
        }

        $this->name = $normalizedName;
        $this->description = $normalizedDescription;
        $this->compatibleSourceTypes = $compatibleSourceTypes;
        $this->defaultFieldKeys = $defaultFieldKeys;
    }

    public function isCompatibleWith(SourceType $sourceType): bool
    {
        if ($this->compatibleSourceTypes === []) {
            return true;
        }

        foreach ($this->compatibleSourceTypes as $compatibleSourceType) {
            if (
                $compatibleSourceType->key === $sourceType->key
                && $compatibleSourceType->schemaVersion === $sourceType->schemaVersion
            ) {
                return true;
            }
        }

        return false;
    }

    public function sameConfigurationAs(self $other): bool
    {
        if (
            $this->name !== $other->name
            || $this->description !== $other->description
            || $this->defaultFieldKeys !== $other->defaultFieldKeys
            || $this->active !== $other->active
            || count($this->compatibleSourceTypes) !== count($other->compatibleSourceTypes)
        ) {
            return false;
        }

        foreach ($this->compatibleSourceTypes as $index => $sourceType) {
            $otherSourceType = $other->compatibleSourceTypes[$index] ?? null;
            if ($otherSourceType === null) {
                return false;
            }
            if (
                $sourceType->key !== $otherSourceType->key
                || $sourceType->schemaVersion !== $otherSourceType->schemaVersion
            ) {
                return false;
            }
        }

        return true;
    }
}
