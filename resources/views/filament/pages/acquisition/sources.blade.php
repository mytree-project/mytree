<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::input.wrapper>
            <x-filament::input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('ui.sources.search') }}"
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

            .mytree-source-id-row {
                display: flex;
                align-items: center;
                gap: 0.375rem;
                margin-top: 0.25rem;
                color: rgb(107 114 128);
                font-size: 0.75rem;
                line-height: 1rem;
            }

            .dark .mytree-source-id-row {
                color: rgb(156 163 175);
            }

            .mytree-source-id {
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
                white-space: nowrap;
            }

            .mytree-source-id-copy {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 0.25rem;
                padding: 0.125rem;
                color: inherit;
            }

            .mytree-source-id-copy:hover,
            .mytree-source-id-copy:focus-visible {
                color: rgb(55 65 81);
            }

            .dark .mytree-source-id-copy:hover,
            .dark .mytree-source-id-copy:focus-visible {
                color: rgb(229 231 235);
            }

            .mytree-source-id-copy:focus-visible {
                outline: 2px solid currentColor;
                outline-offset: 2px;
            }

            .mytree-source-id-copy-feedback {
                white-space: nowrap;
                font-family: ui-sans-serif, system-ui, sans-serif;
                font-size: 0.6875rem;
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
                        <th class="font-medium">{{ __('ui.sources.source') }}</th>
                        <th class="font-medium">{{ __('ui.sources.type') }}</th>
                        <th class="font-medium">{{ __('ui.sources.revision') }}</th>
                        <th class="font-medium">{{ __('ui.sources.metadata') }}</th>
                        <th class="font-medium"><span class="sr-only">{{ __('ui.sources.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @forelse ($this->sources() as $source)
                        <tr wire:key="source-{{ $source->id->value }}">
                            <td>
                                <div class="font-medium text-gray-950 dark:text-white">
                                    {{ $source->name ?? __('ui.sources.untitled') }}
                                </div>
                                <div
                                    class="mytree-source-id-row"
                                    x-data="{ copied: false, timeout: null }"
                                >
                                    <span>ID</span>
                                    <span class="mytree-source-id">{{ $source->id->value }}</span>
                                    <button
                                        type="button"
                                        class="mytree-source-id-copy"
                                        aria-label="{{ __('ui.sources.copy_id') }}"
                                        data-copy-value="{{ $source->id->value }}"
                                        x-on:click="
                                            navigator.clipboard.writeText($el.dataset.copyValue);
                                            copied = true;
                                            clearTimeout(timeout);
                                            timeout = setTimeout(() => copied = false, 1600);
                                        "
                                    >
                                        <x-filament::icon
                                            icon="heroicon-m-clipboard-document"
                                            class="h-4 w-4"
                                        />
                                    </button>
                                    <span
                                        x-cloak
                                        x-show="copied"
                                        x-transition.opacity
                                        class="mytree-source-id-copy-feedback"
                                        role="status"
                                        aria-live="polite"
                                    >
                                        {{ __('ui.sources.copied') }}
                                    </span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap">
                                <div class="font-medium text-gray-950 dark:text-white">{{ $this->sourceTypeLabel($source) }}</div>
                                <div class="mt-1 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $this->sourceTypeDiagnostic($source) }}</div>
                            </td>
                            <td class="whitespace-nowrap">{{ $source->revisionNumber }}</td>
                            <td class="mytree-sources-table-metadata" title="{{ $this->metadataSummary($source) }}">
                                {{ $this->metadataSummary($source) }}
                            </td>
                            <td class="mytree-sources-table-actions">
                                <div class="mytree-sources-table-actions-inner">
                                    <x-filament::link href="{{ \App\Filament\Pages\Acquisition\SourceEditor::getUrl(['source' => $source->id->value]) }}">
                                        {{ __('ui.sources.details') }}
                                    </x-filament::link>
                                    <x-filament::link href="{{ \App\Filament\Pages\Acquisition\StructuredFieldsEditor::getUrl(['source' => $source->id->value]) }}">
                                        {{ __('ui.sources.fields') }}
                                    </x-filament::link>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-10 text-center text-gray-500 dark:text-gray-400">
                                {{ __('ui.sources.empty') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
