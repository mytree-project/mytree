<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::input.wrapper>
            <x-filament::input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Search by Source id, type or metadata"
            />
        </x-filament::input.wrapper>

        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                    <tr>
                        <th class="px-4 py-3 font-medium">Source</th>
                        <th class="px-4 py-3 font-medium">Type</th>
                        <th class="px-4 py-3 font-medium">Revision</th>
                        <th class="px-4 py-3 font-medium">Metadata</th>
                        <th class="px-4 py-3 font-medium"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @forelse ($this->sources() as $source)
                        <tr wire:key="source-{{ $source->id->value }}">
                            <td class="px-4 py-3 font-mono text-xs">{{ $source->id->value }}</td>
                            <td class="px-4 py-3">{{ $source->type->key.'@'.$source->type->schemaVersion }}</td>
                            <td class="px-4 py-3">{{ $source->revisionNumber }}</td>
                            <td class="max-w-xl truncate px-4 py-3" title="{{ $this->metadataSummary($source) }}">
                                {{ $this->metadataSummary($source) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <x-filament::link href="{{ \App\Filament\Pages\Acquisition\SourceEditor::getUrl(['source' => $source->id->value]) }}">
                                    Edit
                                </x-filament::link>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                No Sources match the current search.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>