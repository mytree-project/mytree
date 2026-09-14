<x-filament-panels::page>
    <div class="space-y-10">
        <section class="space-y-4" aria-labelledby="settings-general-heading">
            <div>
                <h2 id="settings-general-heading" class="text-lg font-semibold text-gray-950 dark:text-white">
                    {{ __('ui.settings.general') }}
                </h2>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <div class="text-sm font-medium text-gray-950 dark:text-white">
                            {{ __('ui.settings.language') }}
                        </div>
                        <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ __('ui.settings.language_help') }}
                        </div>
                    </div>

                    @if ($editingLanguage)
                        <div class="flex flex-wrap items-center gap-2">
                            <select
                                wire:model="language"
                                class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/10 dark:bg-gray-950 dark:text-white"
                                aria-label="{{ __('ui.settings.language') }}"
                            >
                                <option value="en">{{ __('ui.settings.english') }}</option>
                                <option value="pl">{{ __('ui.settings.polish') }}</option>
                            </select>

                            <x-filament::button size="sm" wire:click="saveLanguage">
                                {{ __('ui.settings.save') }}
                            </x-filament::button>
                            <x-filament::button size="sm" color="gray" wire:click="cancelEditingLanguage">
                                {{ __('ui.settings.cancel') }}
                            </x-filament::button>
                        </div>
                    @else
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                wire:click="startEditingLanguage"
                                class="text-sm font-medium text-gray-950 hover:text-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:text-white dark:hover:text-primary-400"
                            >
                                {{ $language === 'pl' ? __('ui.settings.polish') : __('ui.settings.english') }}
                            </button>
                            <button
                                type="button"
                                wire:click="startEditingLanguage"
                                class="inline-flex rounded-md p-1 text-gray-400 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:hover:text-gray-200"
                                aria-label="{{ __('ui.settings.edit_language') }}"
                                title="{{ __('ui.settings.edit') }}"
                            >
                                <x-filament::icon icon="heroicon-m-pencil-square" class="h-4 w-4" />
                            </button>
                        </div>
                    @endif
                </div>

                @error('language')
                    <div class="px-5 pb-5 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</div>
                @enderror
            </div>
        </section>

        <section class="space-y-4" aria-labelledby="settings-acquisition-heading">
            <div>
                <h2 id="settings-acquisition-heading" class="text-lg font-semibold text-gray-950 dark:text-white">
                    {{ __('ui.settings.acquisition') }}
                </h2>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                <div class="flex flex-col gap-3 border-b border-gray-200 p-5 sm:flex-row sm:items-start sm:justify-between dark:border-white/10">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                            {{ __('ui.settings.source_type_templates') }}
                        </h3>
                        <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                            {{ __('ui.settings.template_help') }}
                        </p>
                    </div>
                    <x-filament::button size="sm" wire:click="createTemplate">
                        {{ __('ui.settings.create_template') }}
                    </x-filament::button>
                </div>

                <div class="divide-y divide-gray-200 dark:divide-white/10">
                    @forelse ($this->templates() as $template)
                        <button
                            type="button"
                            wire:key="template-{{ $template['template_id'] }}-{{ $template['version'] }}"
                            wire:click="editTemplate('{{ $template['template_id'] }}')"
                            class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary-500/30 dark:hover:bg-white/5"
                        >
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium text-gray-950 dark:text-white">{{ $template['name'] }}</span>
                                    <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-white/10 dark:text-gray-300">
                                        {{ __('ui.settings.version', ['version' => $template['version']]) }}
                                    </span>
                                    <span class="text-xs {{ $template['active'] ? 'text-success-600 dark:text-success-400' : 'text-gray-500 dark:text-gray-400' }}">
                                        {{ $template['active'] ? __('ui.settings.active') : __('ui.settings.inactive') }}
                                    </span>
                                </div>
                                @if ($template['description'])
                                    <div class="mt-1 truncate text-sm text-gray-500 dark:text-gray-400">{{ $template['description'] }}</div>
                                @endif
                                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    @if ($template['compatible_source_types'] === [])
                                        {{ __('ui.settings.all_source_types') }}
                                    @else
                                        {{ collect($template['compatible_source_types'])->pluck('key')->join(', ') }}
                                    @endif
                                    · {{ __('ui.settings.default_field_count', ['count' => count($template['default_fields'])]) }}
                                </div>
                            </div>
                            <x-filament::icon icon="heroicon-m-chevron-right" class="h-5 w-5 shrink-0 text-gray-400" />
                        </button>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                            {{ __('ui.settings.no_templates') }}
                        </div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>

    @if ($templateEditorOpen)
        <div class="fixed inset-0 z-40" role="dialog" aria-modal="true" aria-labelledby="template-editor-title">
            <button
                type="button"
                wire:click="closeTemplateEditor"
                class="absolute inset-0 bg-gray-950/50"
                aria-label="{{ __('ui.settings.close') }}"
            ></button>

            <div class="absolute inset-y-0 right-0 flex w-full max-w-2xl flex-col overflow-hidden bg-white shadow-2xl dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-white/10">
                    <div>
                        <h2 id="template-editor-title" class="text-lg font-semibold text-gray-950 dark:text-white">
                            {{ $templateEditor['template_id'] ? __('ui.settings.edit_template') : __('ui.settings.new_template') }}
                        </h2>
                        @if ($templateEditor['version'])
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ __('ui.settings.version', ['version' => $templateEditor['version']]) }}
                            </p>
                        @endif
                    </div>
                    <button
                        type="button"
                        wire:click="closeTemplateEditor"
                        class="rounded-md p-1 text-gray-400 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:hover:text-gray-200"
                        aria-label="{{ __('ui.settings.close') }}"
                    >
                        <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
                    </button>
                </div>

                <div class="flex-1 space-y-6 overflow-y-auto px-6 py-5">
                    <div>
                        <label for="template-name" class="text-sm font-medium text-gray-950 dark:text-white">
                            {{ __('ui.settings.template_name') }}
                        </label>
                        <input
                            id="template-name"
                            type="text"
                            wire:model="templateEditor.name"
                            class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm dark:border-white/10 dark:bg-gray-950 dark:text-white"
                        >
                        @error('templateEditor.name')
                            <p class="mt-1 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="template-description" class="text-sm font-medium text-gray-950 dark:text-white">
                            {{ __('ui.settings.description') }}
                        </label>
                        <textarea
                            id="template-description"
                            rows="3"
                            wire:model="templateEditor.description"
                            class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm dark:border-white/10 dark:bg-gray-950 dark:text-white"
                        ></textarea>
                    </div>

                    <label class="flex items-center gap-3 text-sm text-gray-950 dark:text-white">
                        <input type="checkbox" wire:model="templateEditor.active" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                        {{ __('ui.settings.active') }}
                    </label>

                    <div class="space-y-3">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('ui.settings.compatible_source_types') }}</h3>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('ui.settings.compatible_source_types_help') }}</p>
                        </div>

                        @foreach ($templateEditor['compatible_source_types'] as $index => $sourceType)
                            <div wire:key="compatible-source-type-{{ $index }}" class="flex items-center gap-2">
                                <input
                                    type="text"
                                    wire:model="templateEditor.compatible_source_types.{{ $index }}.key"
                                    placeholder="civil.birth"
                                    class="block min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm dark:border-white/10 dark:bg-gray-950 dark:text-white"
                                    aria-label="{{ __('ui.settings.source_type_key') }}"
                                >
                                <button
                                    type="button"
                                    wire:click="removeCompatibleSourceType({{ $index }})"
                                    class="rounded-md p-2 text-gray-400 hover:text-danger-600 focus:outline-none focus:ring-2 focus:ring-danger-500/30"
                                    aria-label="{{ __('ui.settings.remove') }}"
                                >
                                    <x-filament::icon icon="heroicon-m-trash" class="h-4 w-4" />
                                </button>
                            </div>
                            @error('templateEditor.compatible_source_types.'.$index.'.key')
                                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                            @enderror
                        @endforeach

                        <x-filament::button type="button" size="sm" color="gray" wire:click="addCompatibleSourceType">
                            {{ __('ui.settings.add_source_type') }}
                        </x-filament::button>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('ui.settings.ordered_default_fields') }}</h3>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('ui.settings.ordered_default_fields_help') }}</p>
                        </div>

                        @foreach ($templateEditor['default_fields'] as $index => $field)
                            <div wire:key="default-field-{{ $index }}" class="flex items-center gap-2">
                                <select
                                    wire:model="templateEditor.default_fields.{{ $index }}.key"
                                    class="block min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm dark:border-white/10 dark:bg-gray-950 dark:text-white"
                                    aria-label="{{ __('ui.settings.supported_field') }}"
                                >
                                    <option value="">{{ __('ui.settings.choose_field') }}</option>
                                    @foreach ($this->supportedFieldOptions() as $group => $options)
                                        <optgroup label="{{ $group }}">
                                            @foreach ($options as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                <button
                                    type="button"
                                    wire:click="removeDefaultField({{ $index }})"
                                    class="rounded-md p-2 text-gray-400 hover:text-danger-600 focus:outline-none focus:ring-2 focus:ring-danger-500/30"
                                    aria-label="{{ __('ui.settings.remove') }}"
                                >
                                    <x-filament::icon icon="heroicon-m-trash" class="h-4 w-4" />
                                </button>
                            </div>
                            @error('templateEditor.default_fields.'.$index.'.key')
                                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                            @enderror
                        @endforeach

                        <x-filament::button type="button" size="sm" color="gray" wire:click="addDefaultField">
                            {{ __('ui.settings.add_field') }}
                        </x-filament::button>
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-gray-200 px-6 py-4 dark:border-white/10">
                    <x-filament::button color="gray" wire:click="closeTemplateEditor">
                        {{ __('ui.settings.cancel') }}
                    </x-filament::button>
                    <x-filament::button wire:click="saveTemplate">
                        {{ __('ui.settings.save') }}
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
