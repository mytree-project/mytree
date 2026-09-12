<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit">
                Save Structured Fields
            </x-filament::button>

            <x-filament::button
                tag="a"
                color="gray"
                href="{{ \App\Filament\Pages\Acquisition\SourceEditor::getUrl(['source' => $sourceId]) }}"
            >
                Edit Source Details
            </x-filament::button>

            <x-filament::button
                tag="a"
                color="gray"
                href="{{ \App\Filament\Pages\Acquisition\Sources::getUrl() }}"
            >
                Back to Sources
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
