<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::input.wrapper>
            <x-filament::input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Search by name, Source id, type or metadata"
            />
        </x-filament::input.wrapper>

        <style>
            .mytree-sources-table-wrapper {
                width: 100%;
                overflow-x: auto;
            }

            .mytree-sources-table {
                width: 100%;
                min-width: 64rem;
                table-layout: fixed;
                border-collapse: collapse;
            }

            .mytree-sources-table th {
                padding: 0.75rem 2rem;
            }

            .mytree-sources-table td {
                padding: 1.25rem 2rem;
                vertical-align: middle;
            }

            .mytree-sources-table-metadata {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .mytree-sources-table-actions {
                white-space: nowrap;
                text-align: right;
            }

            .mytree-sources-table-actions-inner {
                display: flex;
                justify-content: flex-end;
                gap: 1rem;
            }
        </style>

        <div class="mytree-sources-table-wrapper rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
            <table class="mytree-sources-table text-left text-sm">
                <colgroup>
                    <col style="width: 32%">
                    <col style="width: 18%">
                    <col style="width: 10%">
                    <col style="width: 28%">
                    <col style="width: 12%">
                </colgroup>
                <thead class="border-b border-gray-200 bg-gray-50 text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                    <tr>
                        <th class="font-medium">Source</th>
                        <th class="font-medium">Type</th>
                        <th class="font-medium">Revision</th>
                        <th class="font-medium">Metadata</th>
                        <th class="font-medium"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @forelse ($this->sources() as $source)
                        <tr wire:key="source-{{ $source->id->value }}">
                            <td>
                                <div class="font-medium text-gray-950 dark:text-white">
                                    {{ $source->name ?? 'Untitled source' }}
                                </div>
                                <div class="mt-1 font-mono text-xs text-gray-500 dark:text-gray-400" title="{{ $source->id->value }}">
                                    ID {{ substr($source->id->value, 0, 8) }}…
                                </div>
                            </td>
                            <td class="whitespace-nowrap">{{ $source->type->key.'@'.$source->type->schemaVersion }}</td>
                            <td class="whitespace-nowrap">{{ $source->revisionNumber }}</td>
                            <td class="mytree-sources-table-metadata" title="{{ $this->metadataSummary($source) }}">
                                {{ $this->metadataSummary($source) }}
                            </td>
                            <td class="mytree-sources-table-actions">
                                <div class="mytree-sources-table-actions-inner">
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
                            <td colspan="5" class="py-10 text-center text-gray-500 dark:text-gray-400">
                                No Sources match the current search.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
