<x-filament-panels::page>
    <div
        class="space-y-6"
        x-data="{ detailsOpen: false, details: null, detailsLoading: false }"
        @keydown.escape.window="detailsOpen = false"
    >
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

            .mytree-source-details-button {
                font-weight: 600;
                color: rgb(202 138 4);
            }

            .mytree-source-details-button:hover {
                text-decoration: underline;
            }

            .mytree-source-details-button:focus-visible {
                border-radius: 0.25rem;
                outline: 2px solid currentColor;
                outline-offset: 2px;
            }

            .mytree-source-details-overlay {
                position: fixed;
                inset: 0;
                z-index: 50;
            }

            .mytree-source-details-backdrop {
                position: absolute;
                inset: 0;
                background: rgb(17 24 39 / 0.45);
            }

            .mytree-source-details-panel {
                position: absolute;
                top: 0;
                right: 0;
                display: flex;
                width: min(32rem, 100vw);
                height: 100%;
                flex-direction: column;
                overflow-y: auto;
                background: rgb(255 255 255);
                box-shadow: -0.75rem 0 2rem rgb(0 0 0 / 0.15);
            }

            .dark .mytree-source-details-panel {
                background: rgb(17 24 39);
            }

            .mytree-source-details-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                padding: 1.25rem 1.5rem;
                border-bottom: 1px solid rgb(229 231 235);
            }

            .dark .mytree-source-details-header {
                border-color: rgb(55 65 81);
            }

            .mytree-source-details-close {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 0.375rem;
                padding: 0.375rem;
                color: rgb(107 114 128);
            }

            .mytree-source-details-close:hover,
            .mytree-source-details-close:focus-visible {
                color: rgb(17 24 39);
            }

            .dark .mytree-source-details-close:hover,
            .dark .mytree-source-details-close:focus-visible {
                color: rgb(243 244 246);
            }

            .mytree-source-details-close:focus-visible {
                outline: 2px solid currentColor;
                outline-offset: 2px;
            }

            .mytree-source-details-body {
                display: grid;
                gap: 1.25rem;
                padding: 1.5rem;
            }

            .mytree-source-details-row dt {
                margin-bottom: 0.25rem;
                color: rgb(107 114 128);
                font-size: 0.75rem;
                font-weight: 600;
                letter-spacing: 0.05em;
                text-transform: uppercase;
            }

            .dark .mytree-source-details-row dt {
                color: rgb(156 163 175);
            }

            .mytree-source-details-row dd {
                overflow-wrap: anywhere;
            }

            .mytree-source-details-diagnostic {
                margin-top: 0.25rem;
                color: rgb(107 114 128);
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
                font-size: 0.75rem;
            }

            .mytree-source-details-loading {
                padding: 1.5rem;
                color: rgb(107 114 128);
            }

            .mytree-source-details-yaml {
                max-height: 32rem;
                overflow: auto;
                border: 1px solid rgb(229 231 235);
                border-radius: 0.5rem;
                padding: 0.75rem;
                background: rgb(249 250 251);
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
                font-size: 0.75rem;
                line-height: 1.5;
                white-space: pre;
            }

            .dark .mytree-source-details-yaml {
                border-color: rgb(55 65 81);
                background: rgb(3 7 18);
            }
        </style>

        <div class="mytree-sources-table-wrapper rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
            <table class="mytree-sources-table text-left text-sm">
                <colgroup>
                    <col style="width: 32%">
                    <col style="width: 18%">
                    <col style="width: 10%">
                    <col style="width: 26%">
                    <col style="width: 14%">
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
                                        {{ __('ui.sources.edit') }}
                                    </x-filament::link>
                                    <button
                                        type="button"
                                        class="mytree-source-details-button"
                                        data-source-details-trigger="{{ $source->id->value }}"
                                        x-on:click="
                                            details = null;
                                            detailsOpen = true;
                                            detailsLoading = true;
                                            $nextTick(() => $refs.detailsPanel?.focus());
                                            $wire.loadSourceDetails(@js($source->id->value))
                                                .then((loadedDetails) => details = loadedDetails)
                                                .finally(() => detailsLoading = false);
                                        "
                                    >
                                        {{ __('ui.sources.details') }}
                                    </button>
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

        <div
            x-cloak
            x-show="detailsOpen"
            class="mytree-source-details-overlay"
            data-source-details-panel
        >
            <div
                class="mytree-source-details-backdrop"
                aria-hidden="true"
                x-on:click="detailsOpen = false"
            ></div>

            <aside
                x-ref="detailsPanel"
                x-show="detailsOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="translate-x-full"
                class="mytree-source-details-panel"
                role="dialog"
                aria-modal="true"
                aria-labelledby="source-details-title"
                tabindex="-1"
            >
                <header class="mytree-source-details-header">
                    <h2 id="source-details-title" class="text-lg font-semibold text-gray-950 dark:text-white">
                        {{ __('ui.sources.details_title') }}
                    </h2>
                    <button
                        type="button"
                        class="mytree-source-details-close"
                        aria-label="{{ __('ui.sources.close_details') }}"
                        x-on:click="detailsOpen = false"
                    >
                        <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
                    </button>
                </header>

                <div
                    class="mytree-source-details-loading"
                    x-show="detailsLoading"
                    role="status"
                    aria-live="polite"
                >
                    {{ __('ui.sources.details_loading') }}
                </div>

                <dl class="mytree-source-details-body" x-show="details && ! detailsLoading">
                    <div class="mytree-source-details-row">
                        <dt>{{ __('ui.sources.source') }}</dt>
                        <dd class="font-medium text-gray-950 dark:text-white" x-text="details?.name"></dd>
                    </div>
                    <div class="mytree-source-details-row">
                        <dt>{{ __('ui.sources.id') }}</dt>
                        <dd class="font-mono text-sm" x-text="details?.id"></dd>
                    </div>
                    <div class="mytree-source-details-row">
                        <dt>{{ __('ui.sources.type') }}</dt>
                        <dd>
                            <div class="font-medium text-gray-950 dark:text-white" x-text="details?.type"></div>
                            <div class="mytree-source-details-diagnostic" x-text="details?.type_diagnostic"></div>
                        </dd>
                    </div>
                    <div class="mytree-source-details-row">
                        <dt>{{ __('ui.sources.revision') }}</dt>
                        <dd x-text="details?.revision"></dd>
                    </div>
                    <div class="mytree-source-details-row">
                        <dt>{{ __('ui.sources.metadata') }}</dt>
                        <dd x-text="details?.metadata"></dd>
                    </div>
                    <div class="mytree-source-details-row">
                        <dt>{{ __('ui.sources.evidence_graph_yaml') }}</dt>
                        <dd>
                            <pre class="mytree-source-details-yaml" data-source-details-yaml x-text="details?.graph_yaml"></pre>
                        </dd>
                    </div>
                </dl>
            </aside>
        </div>
    </div>
</x-filament-panels::page>
