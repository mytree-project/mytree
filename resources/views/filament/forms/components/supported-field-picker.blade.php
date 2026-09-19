@php
    $groups = $getGroups();
    $labels = [];
    $allSearchTerms = [];

    foreach ($groups as $group) {
        foreach ($group['fields'] as $fieldOption) {
            $labels[$fieldOption['key']] = $fieldOption['label'];
            $allSearchTerms[] = [$fieldOption['label'], $fieldOption['key']];
        }
    }
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        class="supported-field-palette"
        data-supported-field-palette
        data-supported-field-palette-name="{{ $getName() }}"
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$getStatePath()}')") }},
            query: '',
            labels: @js($labels),
            normalize(value) {
                return String(value ?? '')
                    .toLocaleLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '');
            },
            matches(label, key) {
                const query = this.normalize(this.query.trim());

                return query === ''
                    || this.normalize(label).includes(query)
                    || this.normalize(key).includes(query);
            },
            groupMatches(terms) {
                return terms.some(([label, key]) => this.matches(label, key));
            },
            moveFocus(delta) {
                const buttons = Array.from(
                    this.$el.querySelectorAll('[data-supported-field-palette-option]')
                ).filter((button) => button.offsetParent !== null);

                if (buttons.length === 0) {
                    return;
                }

                const current = buttons.indexOf(document.activeElement);
                const next = current === -1
                    ? (delta > 0 ? 0 : buttons.length - 1)
                    : (current + delta + buttons.length) % buttons.length;

                buttons[next].focus();
            },
        }"
    >
        <x-filament::dropdown
            placement="bottom-start"
            width="7xl"
            max-height="70vh"
            shift
            teleport
        >
            <x-slot name="trigger">
                <button
                    type="button"
                    class="supported-field-palette-trigger"
                    data-supported-field-palette-trigger
                >
                    <span
                        class="supported-field-palette-trigger-label"
                        x-text="labels[state] ?? @js(__('supported_fields.picker.choose'))"
                    ></span>

                    <svg
                        aria-hidden="true"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        class="supported-field-palette-chevron"
                    >
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.51a.75.75 0 0 1-1.08 0l-4.25-4.51a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                    </svg>
                </button>
            </x-slot>

            <div
                class="supported-field-palette-panel"
                data-supported-field-palette-panel
                x-on:keydown.escape.stop="query = ''"
                x-on:keydown.arrow-down.prevent="moveFocus(1)"
                x-on:keydown.arrow-up.prevent="moveFocus(-1)"
            >
                <div class="supported-field-palette-toolbar">
                    <strong>{{ __('supported_fields.picker.title') }}</strong>

                    <label class="supported-field-palette-search">
                        <span class="sr-only">{{ __('supported_fields.picker.search_label') }}</span>
                        <input
                            type="search"
                            data-supported-field-palette-search
                            x-model="query"
                            placeholder="{{ __('supported_fields.picker.search_placeholder') }}"
                            autocomplete="off"
                        >
                    </label>
                </div>

                <div class="supported-field-palette-columns">
                    @foreach ($groups as $group)
                        @php
                            $groupSearchTerms = array_map(
                                static fn (array $fieldOption): array => [$fieldOption['label'], $fieldOption['key']],
                                $group['fields'],
                            );
                        @endphp

                        <section
                            class="supported-field-palette-group"
                            data-supported-field-palette-group="{{ $group['key'] }}"
                            x-show="groupMatches(@js($groupSearchTerms))"
                        >
                            <h3>{{ $group['label'] }}</h3>

                            <div class="supported-field-palette-options">
                                @foreach ($group['fields'] as $fieldOption)
                                    <button
                                        type="button"
                                        @class([
                                            'supported-field-palette-option',
                                            'supported-field-palette-option-event-context' => $fieldOption['event_context'],
                                        ])
                                        data-supported-field-palette-option
                                        data-supported-field-key="{{ $fieldOption['key'] }}"
                                        x-show="matches(@js($fieldOption['label']), @js($fieldOption['key']))"
                                        x-bind:class="{ 'supported-field-palette-option-selected': state === @js($fieldOption['key']) }"
                                        x-bind:aria-pressed="state === @js($fieldOption['key']) ? 'true' : 'false'"
                                        x-on:click="
                                            state = @js($fieldOption['key']);
                                            query = '';
                                            close();
                                        "
                                    >
                                        <span class="supported-field-palette-option-heading">
                                            <strong>{{ $fieldOption['label'] }}</strong>

                                            @if ($fieldOption['event_context'])
                                                <span class="supported-field-palette-badge supported-field-palette-badge-event">
                                                    {{ __('supported_fields.picker.event_context') }}
                                                </span>
                                            @endif

                                            @if ($fieldOption['repeatable'])
                                                <span class="supported-field-palette-badge">
                                                    {{ __('supported_fields.picker.repeatable') }}
                                                </span>
                                            @endif
                                        </span>

                                        <code>{{ $fieldOption['key'] }}</code>

                                        @if ($fieldOption['help'] !== null)
                                            <span class="supported-field-palette-help">
                                                {{ $fieldOption['help'] }}
                                            </span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>

                <p
                    class="supported-field-palette-empty"
                    x-show="! groupMatches(@js($allSearchTerms))"
                >
                    {{ __('supported_fields.picker.empty') }}
                </p>
            </div>
        </x-filament::dropdown>
    </div>

    @once
        <style>
            .supported-field-palette { min-width: 0; }
            .supported-field-palette .fi-dropdown { width: 100%; }
            .supported-field-palette .fi-dropdown-trigger { width: 100%; }

            .supported-field-palette-trigger {
                display: flex;
                width: 100%;
                min-height: 2.5rem;
                align-items: center;
                justify-content: space-between;
                gap: .75rem;
                border: 1px solid rgb(209 213 219);
                border-radius: .5rem;
                padding: .5rem .65rem;
                background: transparent;
                text-align: left;
            }

            .dark .supported-field-palette-trigger {
                border-color: rgb(75 85 99);
            }

            .supported-field-palette-trigger:focus-visible,
            .supported-field-palette-option:focus-visible,
            .supported-field-palette-search input:focus-visible {
                outline: 2px solid currentColor;
                outline-offset: 2px;
            }

            .supported-field-palette-trigger-label {
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .supported-field-palette-chevron {
                width: 1rem;
                height: 1rem;
                flex: 0 0 auto;
                opacity: .55;
            }

            .supported-field-palette-panel {
                display: grid;
                min-width: min(78rem, calc(100vw - 2rem));
                gap: .8rem;
                padding: .85rem;
                background: rgb(255 255 255);
            }

            .dark .supported-field-palette-panel {
                background: rgb(17 24 39);
            }

            .supported-field-palette-toolbar {
                display: grid;
                grid-template-columns: minmax(10rem, .6fr) minmax(16rem, 1fr);
                align-items: center;
                gap: .75rem;
            }

            .supported-field-palette-search input {
                width: 100%;
                border: 1px solid rgb(209 213 219);
                border-radius: .5rem;
                padding: .45rem .6rem;
                background: transparent;
            }

            .dark .supported-field-palette-search input {
                border-color: rgb(75 85 99);
            }

            .supported-field-palette-columns {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr));
                align-items: start;
                gap: .7rem;
            }

            .supported-field-palette-group {
                display: grid;
                min-width: 0;
                gap: .45rem;
            }

            .supported-field-palette-group h3 {
                margin: 0;
                padding-bottom: .35rem;
                border-bottom: 1px solid rgb(229 231 235);
                font-size: .78rem;
                font-weight: 700;
            }

            .dark .supported-field-palette-group h3 {
                border-color: rgb(55 65 81);
            }

            .supported-field-palette-options {
                display: grid;
                gap: .3rem;
            }

            .supported-field-palette-option {
                display: grid;
                width: 100%;
                gap: .18rem;
                border: 1px solid transparent;
                border-radius: .45rem;
                padding: .45rem .5rem;
                text-align: left;
            }

            .supported-field-palette-option:hover,
            .supported-field-palette-option-selected {
                border-color: rgb(209 213 219);
                background: rgb(249 250 251);
            }

            .dark .supported-field-palette-option:hover,
            .dark .supported-field-palette-option-selected {
                border-color: rgb(75 85 99);
                background: rgb(31 41 55);
            }

            .supported-field-palette-option-event-context {
                border-style: dashed;
                border-color: rgb(156 163 175);
            }

            .supported-field-palette-option-heading {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: .3rem;
            }

            .supported-field-palette-option code {
                color: rgb(107 114 128);
                font-size: .67rem;
                overflow-wrap: anywhere;
            }

            .supported-field-palette-help {
                color: rgb(107 114 128);
                font-size: .69rem;
                line-height: 1.35;
            }

            .supported-field-palette-badge {
                display: inline-flex;
                border: 1px solid currentColor;
                border-radius: 999px;
                padding: .05rem .35rem;
                font-size: .62rem;
                font-weight: 650;
            }

            .supported-field-palette-empty {
                margin: 0;
                padding: .75rem;
                color: rgb(107 114 128);
                text-align: center;
            }

            @media (max-width: 768px) {
                .supported-field-palette-panel {
                    min-width: min(34rem, calc(100vw - 1rem));
                }

                .supported-field-palette-toolbar {
                    grid-template-columns: 1fr;
                }

                .supported-field-palette-columns {
                    grid-template-columns: 1fr;
                }
            }
        </style>
    @endonce
</x-dynamic-component>
