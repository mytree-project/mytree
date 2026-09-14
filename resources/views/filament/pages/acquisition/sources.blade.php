<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::input.wrapper>
            <x-filament::input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Search by name, Source id, type or metadata"
            />
        </x-filament::input.wrapper>

        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
            <table class="min-w-[64rem] w-full table-auto text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                    <tr>
                        <th class="min-w-[22rem] px-8 py-3 font-medium">Source</th>
                        <th class="min-w-44 px-8 py-3 font-medium">Type</th>
                        <th class="w-28 px-8 py-3 font-medium">Revision</th>
                        <th class="min-w-80 px-8 py-3 font-medium">Metadata</th>
                        <th class="w-44 px-8 py-3 font-medium"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @forelse ($this->sources() as $source)
                        <tr wire:key="source-{{ $source->id->value }}">
                            <td class="px-8 py-5">
                                <div class="font-medium text-gray-950 dark:text-white">
                                    {{ $source->name ?? 'Untitled source' }}
                                </div>
                                <div class="mt-1 font-mono text-xs text-gray-500 dark:text-gray-400" title="{{ $source->id->value }}">
                                    ID {{ substr($source->id->value, 0, 8) }}…
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-8 py-5">{{ $source->type->key.'@'.$source->type->schemaVersion }}</td>
                            <td class="whitespace-nowrap px-8 py-5">{{ $source->revisionNumber }}</td>
                            <td class="max-w-2xl truncate px-8 py-5" title="{{ $this->metadataSummary($source) }}">
                                {{ $this->metadataSummary($source) }}
                            </td>
                            <td class="whitespace-nowrap px-8 py-5 text-right">
                                <div class="flex justify-end gap-4">
                                    <x-filament::link href="{{ \App\Filament\Pages\Acquisition\SourceEditor::getUrl(['source' => $source->id->value]) }}">
                                        Details
                                    </x-filament::link>
                                    <x-filament::link href="{{ \App\Filament\Pages\Acquisition\StructuredFieldsEditor::getUrl(['source' => $source->id->value]) }}">
                                        Fields
                                    </x-filament::link>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-8 py-10 text-center text-gray-500 dark:text-gray-400">
                                No Sources match the current search.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
