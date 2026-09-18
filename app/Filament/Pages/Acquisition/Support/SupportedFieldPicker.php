<?php

declare(strict_types=1);

namespace App\Filament\Pages\Acquisition\Support;

use Closure;
use Filament\Forms\Components\Field;

final class SupportedFieldPicker extends Field
{
    protected string $view = 'filament.forms.components.supported-field-picker';

    /**
     * @var array<int, array<string, mixed>>|Closure
     */
    protected array|Closure $groups = [];

    /**
     * @param  array<int, array<string, mixed>>|Closure  $groups
     */
    public function groups(array|Closure $groups): static
    {
        $this->groups = $groups;

        return $this;
    }

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     fields: list<array{
     *         key: string,
     *         label: string,
     *         help: ?string,
     *         repeatable: bool,
     *         event_context: bool
     *     }>
     * }>
     */
    public function getGroups(): array
    {
        /** @var mixed $groups */
        $groups = $this->evaluate($this->groups);

        return is_array($groups) ? array_values($groups) : [];
    }
}
