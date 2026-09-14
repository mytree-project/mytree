<x-filament-panels::page>
    <style>
        .mytree-settings {
            display: flex;
            flex-direction: column;
            gap: 2.5rem;
        }

        .mytree-settings-section {
            display: flex;
            flex-direction: column;
            gap: 0.9rem;
        }

        .mytree-settings-section-title {
            margin: 0;
            font-size: 1.125rem;
            line-height: 1.75rem;
            font-weight: 650;
            color: rgb(17 24 39);
        }

        .dark .mytree-settings-section-title {
            color: rgb(255 255 255);
        }

        .mytree-settings-card {
            overflow: hidden;
            border: 1px solid rgb(229 231 235);
            border-radius: 0.75rem;
            background: rgb(255 255 255);
            box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
        }

        .dark .mytree-settings-card {
            border-color: rgb(255 255 255 / 0.1);
            background: rgb(17 24 39);
        }

        .mytree-setting-row,
        .mytree-template-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            padding: 1.25rem 1.5rem;
        }

        .mytree-template-card-header {
            align-items: flex-start;
            border-bottom: 1px solid rgb(229 231 235);
        }

        .dark .mytree-template-card-header {
            border-color: rgb(255 255 255 / 0.1);
        }

        .mytree-setting-copy,
        .mytree-template-header-copy,
        .mytree-template-copy {
            min-width: 0;
        }

        .mytree-setting-label,
        .mytree-template-heading {
            color: rgb(17 24 39);
            font-size: 0.875rem;
            line-height: 1.25rem;
            font-weight: 600;
        }

        .dark .mytree-setting-label,
        .dark .mytree-template-heading {
            color: rgb(255 255 255);
        }

        .mytree-setting-help,
        .mytree-template-help,
        .mytree-template-description,
        .mytree-template-meta,
        .mytree-drawer-help,
        .mytree-drawer-version {
            color: rgb(107 114 128);
        }

        .dark .mytree-setting-help,
        .dark .mytree-template-help,
        .dark .mytree-template-description,
        .dark .mytree-template-meta,
        .dark .mytree-drawer-help,
        .dark .mytree-drawer-version {
            color: rgb(156 163 175);
        }

        .mytree-setting-help,
        .mytree-template-help {
            margin-top: 0.25rem;
            max-width: 52rem;
            font-size: 0.875rem;
            line-height: 1.35rem;
        }

        .mytree-setting-actions,
        .mytree-language-display,
        .mytree-drawer-actions,
        .mytree-repeater-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .mytree-language-value {
            border: 0;
            background: transparent;
            padding: 0.35rem 0.15rem;
            color: rgb(17 24 39);
            font: inherit;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
        }

        .dark .mytree-language-value {
            color: rgb(255 255 255);
        }

        .mytree-language-value:hover,
        .mytree-icon-button:hover {
            color: rgb(217 119 6);
        }

        .mytree-icon-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            border: 0;
            border-radius: 0.5rem;
            background: transparent;
            color: rgb(107 114 128);
            cursor: pointer;
        }

        .mytree-icon-button:hover {
            background: rgb(249 250 251);
        }

        .dark .mytree-icon-button:hover {
            background: rgb(255 255 255 / 0.05);
        }

        .mytree-icon-button svg,
        .mytree-template-chevron svg {
            width: 1.1rem;
            height: 1.1rem;
        }

        .mytree-settings-select,
        .mytree-drawer-input,
        .mytree-drawer-textarea,
        .mytree-drawer-select {
            width: 100%;
            border: 1px solid rgb(209 213 219);
            border-radius: 0.5rem;
            background: rgb(255 255 255);
            padding: 0.55rem 0.75rem;
            color: rgb(17 24 39);
            font-size: 0.875rem;
            line-height: 1.25rem;
            box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
        }

        .mytree-settings-select {
            width: auto;
            min-width: 9rem;
        }

        .dark .mytree-settings-select,
        .dark .mytree-drawer-input,
        .dark .mytree-drawer-textarea,
        .dark .mytree-drawer-select {
            border-color: rgb(255 255 255 / 0.14);
            background: rgb(3 7 18);
            color: rgb(255 255 255);
        }

        .mytree-settings-select:focus,
        .mytree-drawer-input:focus,
        .mytree-drawer-textarea:focus,
        .mytree-drawer-select:focus {
            outline: 2px solid rgb(245 158 11 / 0.25);
            outline-offset: 1px;
            border-color: rgb(245 158 11);
        }

        .mytree-error {
            padding: 0 1.5rem 1.25rem;
            color: rgb(220 38 38);
            font-size: 0.875rem;
        }

        .mytree-template-list {
            display: flex;
            flex-direction: column;
        }

        .mytree-template-row {
            display: flex;
            width: 100%;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            border: 0;
            border-top: 1px solid rgb(229 231 235);
            background: transparent;
            padding: 1rem 1.5rem;
            text-align: left;
            cursor: pointer;
        }

        .mytree-template-row:first-child {
            border-top: 0;
        }

        .dark .mytree-template-row {
            border-color: rgb(255 255 255 / 0.1);
        }

        .mytree-template-row:hover,
        .mytree-template-row:focus-visible {
            background: rgb(249 250 251);
            outline: none;
        }

        .dark .mytree-template-row:hover,
        .dark .mytree-template-row:focus-visible {
            background: rgb(255 255 255 / 0.04);
        }

        .mytree-template-title-line {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
        }

        .mytree-template-name {
            color: rgb(17 24 39);
            font-size: 0.925rem;
            font-weight: 600;
        }

        .dark .mytree-template-name {
            color: rgb(255 255 255);
        }

        .mytree-template-badge {
            border-radius: 0.375rem;
            background: rgb(243 244 246);
            padding: 0.15rem 0.45rem;
            color: rgb(75 85 99);
            font-size: 0.75rem;
        }

        .dark .mytree-template-badge {
            background: rgb(255 255 255 / 0.08);
            color: rgb(209 213 219);
        }

        .mytree-template-status {
            font-size: 0.75rem;
            font-weight: 600;
        }

        .mytree-template-status-active {
            color: rgb(22 163 74);
        }

        .mytree-template-status-inactive {
            color: rgb(107 114 128);
        }

        .mytree-template-description {
            margin-top: 0.3rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 0.875rem;
        }

        .mytree-template-meta {
            margin-top: 0.45rem;
            font-size: 0.75rem;
        }

        .mytree-template-chevron {
            flex: none;
            color: rgb(156 163 175);
        }

        .mytree-empty {
            padding: 2rem 1.5rem;
            text-align: center;
            color: rgb(107 114 128);
            font-size: 0.875rem;
        }

        .mytree-template-dialog {
            position: fixed;
            inset: 0;
            z-index: 60;
        }

        .mytree-template-dialog-backdrop {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            border: 0;
            background: rgb(17 24 39 / 0.55);
            cursor: default;
        }

        .mytree-template-drawer {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            display: flex;
            width: min(100%, 42rem);
            flex-direction: column;
            background: rgb(255 255 255);
            box-shadow: -12px 0 32px rgb(0 0 0 / 0.18);
        }

        .dark .mytree-template-drawer {
            background: rgb(17 24 39);
        }

        .mytree-drawer-header,
        .mytree-drawer-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgb(229 231 235);
        }

        .mytree-drawer-footer {
            justify-content: flex-end;
            border-top: 1px solid rgb(229 231 235);
            border-bottom: 0;
        }

        .dark .mytree-drawer-header,
        .dark .mytree-drawer-footer {
            border-color: rgb(255 255 255 / 0.1);
        }

        .mytree-drawer-title {
            margin: 0;
            color: rgb(17 24 39);
            font-size: 1.1rem;
            line-height: 1.5rem;
            font-weight: 650;
        }

        .dark .mytree-drawer-title {
            color: rgb(255 255 255);
        }

        .mytree-drawer-version {
            margin-top: 0.2rem;
            font-size: 0.75rem;
        }

        .mytree-drawer-body {
            flex: 1 1 auto;
            overflow-y: auto;
            padding: 1.25rem 1.5rem 2rem;
        }

        .mytree-drawer-stack {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .mytree-field-label,
        .mytree-drawer-section-title {
            display: block;
            margin-bottom: 0.4rem;
            color: rgb(17 24 39);
            font-size: 0.875rem;
            font-weight: 600;
        }

        .dark .mytree-field-label,
        .dark .mytree-drawer-section-title {
            color: rgb(255 255 255);
        }

        .mytree-drawer-help {
            margin: -0.15rem 0 0.75rem;
            font-size: 0.75rem;
            line-height: 1.1rem;
        }

        .mytree-checkbox-row {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            color: rgb(17 24 39);
            font-size: 0.875rem;
        }

        .dark .mytree-checkbox-row {
            color: rgb(255 255 255);
        }

        .mytree-repeater {
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }

        .mytree-repeater-row > :first-child {
            min-width: 0;
            flex: 1 1 auto;
        }

        .mytree-row-error {
            margin-top: 0.35rem;
            color: rgb(220 38 38);
            font-size: 0.8rem;
        }

        @media (max-width: 640px) {
            .mytree-setting-row,
            .mytree-template-card-header {
                align-items: stretch;
                flex-direction: column;
            }

            .mytree-setting-actions,
            .mytree-language-display {
                justify-content: flex-start;
            }

            .mytree-template-drawer {
                width: 100%;
            }
        }
    </style>

    <div class="mytree-settings">
        <section class="mytree-settings-section" aria-labelledby="settings-general-heading">
            <h2 id="settings-general-heading" class="mytree-settings-section-title">
                {{ __('ui.settings.general') }}
            </h2>

            <div class="mytree-settings-card">
                <div class="mytree-setting-row">
                    <div class="mytree-setting-copy">
                        <div class="mytree-setting-label">{{ __('ui.settings.language') }}</div>
                        <div class="mytree-setting-help">{{ __('ui.settings.language_help') }}</div>
                    </div>

                    @if ($editingLanguage)
                        <div class="mytree-setting-actions">
                            <select wire:model="language" class="mytree-settings-select" aria-label="{{ __('ui.settings.language') }}">
                                <option value="en">{{ __('ui.settings.english') }}</option>
                                <option value="pl">{{ __('ui.settings.polish') }}</option>
                            </select>
                            <x-filament::button size="sm" wire:click="saveLanguage">{{ __('ui.settings.save') }}</x-filament::button>
                            <x-filament::button size="sm" color="gray" wire:click="cancelEditingLanguage">{{ __('ui.settings.cancel') }}</x-filament::button>
                        </div>
                    @else
                        <div class="mytree-language-display">
                            <button type="button" wire:click="startEditingLanguage" class="mytree-language-value">
                                {{ $language === 'pl' ? __('ui.settings.polish') : __('ui.settings.english') }}
                            </button>
                            <button type="button" wire:click="startEditingLanguage" class="mytree-icon-button" aria-label="{{ __('ui.settings.edit_language') }}" title="{{ __('ui.settings.edit') }}">
                                <x-filament::icon icon="heroicon-m-pencil-square" />
                            </button>
                        </div>
                    @endif
                </div>

                @error('language')
                    <div class="mytree-error">{{ $message }}</div>
                @enderror
            </div>
        </section>

        <section class="mytree-settings-section" aria-labelledby="settings-acquisition-heading">
            <h2 id="settings-acquisition-heading" class="mytree-settings-section-title">
                {{ __('ui.settings.acquisition') }}
            </h2>

            <div class="mytree-settings-card">
                <div class="mytree-template-card-header">
                    <div class="mytree-template-header-copy">
                        <div class="mytree-template-heading">{{ __('ui.settings.source_type_templates') }}</div>
                        <p class="mytree-template-help">{{ __('ui.settings.template_help') }}</p>
                    </div>
                    <x-filament::button size="sm" wire:click="createTemplate">{{ __('ui.settings.create_template') }}</x-filament::button>
                </div>

                <div class="mytree-template-list">
                    @forelse ($this->templates() as $template)
                        <button
                            type="button"
                            wire:key="template-{{ $template['template_id'] }}-{{ $template['version'] }}"
                            wire:click="editTemplate('{{ $template['template_id'] }}')"
                            class="mytree-template-row"
                        >
                            <div class="mytree-template-copy">
                                <div class="mytree-template-title-line">
                                    <span class="mytree-template-name">{{ $template['name'] }}</span>
                                    <span class="mytree-template-badge">{{ __('ui.settings.version', ['version' => $template['version']]) }}</span>
                                    <span class="mytree-template-status {{ $template['active'] ? 'mytree-template-status-active' : 'mytree-template-status-inactive' }}">
                                        {{ $template['active'] ? __('ui.settings.active') : __('ui.settings.inactive') }}
                                    </span>
                                </div>
                                @if ($template['description'])
                                    <div class="mytree-template-description">{{ $template['description'] }}</div>
                                @endif
                                <div class="mytree-template-meta">
                                    @if ($template['compatible_source_types'] === [])
                                        {{ __('ui.settings.all_source_types') }}
                                    @else
                                        {{ collect($template['compatible_source_types'])->pluck('key')->join(', ') }}
                                    @endif
                                    · {{ __('ui.settings.default_field_count', ['count' => count($template['default_fields'])]) }}
                                </div>
                            </div>
                            <span class="mytree-template-chevron"><x-filament::icon icon="heroicon-m-chevron-right" /></span>
                        </button>
                    @empty
                        <div class="mytree-empty">{{ __('ui.settings.no_templates') }}</div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>

    @if ($templateEditorOpen)
        <div class="mytree-template-dialog" role="dialog" aria-modal="true" aria-labelledby="template-editor-title">
            <button type="button" wire:click="closeTemplateEditor" class="mytree-template-dialog-backdrop" aria-label="{{ __('ui.settings.close') }}"></button>

            <div class="mytree-template-drawer">
                <div class="mytree-drawer-header">
                    <div>
                        <h2 id="template-editor-title" class="mytree-drawer-title">
                            {{ $templateEditor['template_id'] ? __('ui.settings.edit_template') : __('ui.settings.new_template') }}
                        </h2>
                        @if ($templateEditor['version'])
                            <div class="mytree-drawer-version">{{ __('ui.settings.version', ['version' => $templateEditor['version']]) }}</div>
                        @endif
                    </div>
                    <button type="button" wire:click="closeTemplateEditor" class="mytree-icon-button" aria-label="{{ __('ui.settings.close') }}">
                        <x-filament::icon icon="heroicon-m-x-mark" />
                    </button>
                </div>

                <div class="mytree-drawer-body">
                    <div class="mytree-drawer-stack">
                        <div>
                            <label for="template-name" class="mytree-field-label">{{ __('ui.settings.template_name') }}</label>
                            <input id="template-name" type="text" wire:model="templateEditor.name" class="mytree-drawer-input">
                            @error('templateEditor.name')
                                <div class="mytree-row-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label for="template-description" class="mytree-field-label">{{ __('ui.settings.description') }}</label>
                            <textarea id="template-description" rows="3" wire:model="templateEditor.description" class="mytree-drawer-textarea"></textarea>
                        </div>

                        <label class="mytree-checkbox-row">
                            <input type="checkbox" wire:model="templateEditor.active">
                            {{ __('ui.settings.active') }}
                        </label>

                        <div>
                            <div class="mytree-drawer-section-title">{{ __('ui.settings.compatible_source_types') }}</div>
                            <p class="mytree-drawer-help">{{ __('ui.settings.compatible_source_types_help') }}</p>
                            <div class="mytree-repeater">
                                @foreach ($templateEditor['compatible_source_types'] as $index => $sourceType)
                                    <div wire:key="compatible-source-type-{{ $index }}">
                                        <div class="mytree-repeater-row">
                                            <input
                                                type="text"
                                                wire:model="templateEditor.compatible_source_types.{{ $index }}.key"
                                                placeholder="civil.birth"
                                                class="mytree-drawer-input"
                                                aria-label="{{ __('ui.settings.source_type_key') }}"
                                            >
                                            <button type="button" wire:click="removeCompatibleSourceType({{ $index }})" class="mytree-icon-button" aria-label="{{ __('ui.settings.remove') }}">
                                                <x-filament::icon icon="heroicon-m-trash" />
                                            </button>
                                        </div>
                                        @error('templateEditor.compatible_source_types.'.$index.'.key')
                                            <div class="mytree-row-error">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>
                            <div style="margin-top: .75rem">
                                <x-filament::button type="button" size="sm" color="gray" wire:click="addCompatibleSourceType">{{ __('ui.settings.add_source_type') }}</x-filament::button>
                            </div>
                        </div>

                        <div>
                            <div class="mytree-drawer-section-title">{{ __('ui.settings.ordered_default_fields') }}</div>
                            <p class="mytree-drawer-help">{{ __('ui.settings.ordered_default_fields_help') }}</p>
                            <div class="mytree-repeater">
                                @foreach ($templateEditor['default_fields'] as $index => $field)
                                    <div wire:key="default-field-{{ $index }}">
                                        <div class="mytree-repeater-row">
                                            <select
                                                wire:model="templateEditor.default_fields.{{ $index }}.key"
                                                class="mytree-drawer-select"
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
                                            <button type="button" wire:click="removeDefaultField({{ $index }})" class="mytree-icon-button" aria-label="{{ __('ui.settings.remove') }}">
                                                <x-filament::icon icon="heroicon-m-trash" />
                                            </button>
                                        </div>
                                        @error('templateEditor.default_fields.'.$index.'.key')
                                            <div class="mytree-row-error">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>
                            <div style="margin-top: .75rem">
                                <x-filament::button type="button" size="sm" color="gray" wire:click="addDefaultField">{{ __('ui.settings.add_field') }}</x-filament::button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mytree-drawer-footer">
                    <x-filament::button color="gray" wire:click="closeTemplateEditor">{{ __('ui.settings.cancel') }}</x-filament::button>
                    <x-filament::button wire:click="saveTemplate">{{ __('ui.settings.save') }}</x-filament::button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
