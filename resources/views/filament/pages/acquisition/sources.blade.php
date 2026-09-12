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
            <table class="min-w-[56rem] w-full table-auto text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                    <tr>
                        <th class="w-[28rem] px-6 py-3 font-medium">Source</th>
                        <th class="min-w-40 px-6 py-3 font-medium">Type</th>
                        <th class="w-28 px-6 py-3 font-medium">Revision</th>
                        <th class="min-w-72 px-6 py-3 font-medium">Metadata</th>
                        <th class="w-24 px-6 py-3 font-medium"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @forelse ($this->sources() as $source)
                        <tr wire:key="source-{{ $source->id->value }}">
                            <td class="whitespace-nowrap px-6 py-4 font-mono text-xs">{{ $source->id->value }}</td>
                            <td class="whitespace-nowrap px-6 py-4">{{ $source->type->key.'@'.$source->type->schemaVersion }}</td>
                            <td class="whitespace-nowrap px-6 py-4">{{ $source->revisionNumber }}</td>
                            <td class="max-w-xl truncate px-6 py-4" title="{{ $this->metadataSummary($source) }}">
                                {{ $this->metadataSummary($source) }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right">
                                <x-filament::link href="{{ \App\Filament\Pages\Acquisition\SourceEditor::getUrl(['source' => $source->id->value]) }}">
                                    Edit
                                </x-filament::link>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                No Sources match the current search.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>