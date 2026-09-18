@php
    $supportedFieldGroups = $this->supportedFieldPickerGroups();
    $supportedFieldSearchTerms = [];

    foreach ($supportedFieldGroups as $group) {
        foreach ($group['fields'] as $field) {
            $supportedFieldSearchTerms[] = [$field['label'], $field['key']];
        }
    }
@endphp

<div
    class="supported-field-picker"
    data-supported-field-picker
    x-data="{
        open: false,
        query: '',
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
                this.$refs.panel.querySelectorAll('[data-supported-field-picker-add]')
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
    <button
        type="button"
        class="supported-field-picker-trigger"
        data-supported-field-picker-trigger
        x-ref="trigger"
        @click="
            open = ! open;
            if (open) {
                $nextTick(() => $refs.search.focus());
            }
        "
        :aria-expanded="open.toString()"
        aria-controls="supported-field-picker-panel"
    >
        <span>{{ __('supported_fields.picker.open') }}</span>
        <span aria-hidden="true">＋</span>
    </button>

    <p class="supported-field-picker-intro">
        {{ __('supported_fields.picker.description') }}
    </p>

    <div
        id="supported-field-picker-panel"
        class="supported-field-picker-panel"
        data-supported-field-picker-panel
        x-ref="panel"
        x-show="open"
        x-cloak
        role="region"
        aria-labelledby="supported-field-picker-title"
        @keydown.escape.stop="
            open = false;
            query = '';
            $refs.trigger.focus();
        "
        @keydown.arrow-down.prevent="moveFocus(1)"
        @keydown.arrow-up.prevent="moveFocus(-1)"
    >
        <div class="supported-field-picker-header">
            <strong id="supported-field-picker-title">
                {{ __('supported_fields.picker.title') }}
            </strong>

            <label class="supported-field-picker-search">
                <span>{{ __('supported_fields.picker.search_label') }}</span>
                <input
                    type="search"
                    data-supported-field-picker-search
                    x-ref="search"
                    x-model="query"
                    placeholder="{{ __('supported_fields.picker.search_placeholder') }}"
                    autocomplete="off"
                >
            </label>
        </div>

        <div class="supported-field-picker-groups">
            @foreach ($supportedFieldGroups as $group)
                @php
                    $groupSearchTerms = array_map(
                        static fn (array $field): array => [$field['label'], $field['key']],
                        $group['fields'],
                    );
                @endphp

                <section
                    class="supported-field-picker-group"
                    data-supported-field-picker-group="{{ $group['key'] }}"
                    x-show="groupMatches(@js($groupSearchTerms))"
                >
                    <h3>{{ $group['label'] }}</h3>

                    <div class="supported-field-picker-items">
                        @foreach ($group['fields'] as $field)
                            <article
                                @class([
                                    'supported-field-picker-item',
                                    'supported-field-picker-item-event-context' => $field['event_context'],
                                ])
                                data-supported-field-picker-item="{{ $field['key'] }}"
                                x-show="matches(@js($field['label']), @js($field['key']))"
                            >
                                <div class="supported-field-picker-item-main">
                                    <div class="supported-field-picker-item-title-row">
                                        <strong>{{ $field['label'] }}</strong>

                                        @if ($field['event_context'])
                                            <span class="supported-field-picker-badge supported-field-picker-badge-event">
                                                {{ __('supported_fields.picker.event_context') }}
                                            </span>
                                        @endif

                                        @if ($field['repeatable'])
                                            <span class="supported-field-picker-badge">
                                                {{ __('supported_fields.picker.repeatable') }}
                                            </span>
                                        @endif
                                    </div>

                                    <code title="{{ __('supported_fields.picker.canonical_key', ['key' => $field['key']]) }}">
                                        {{ $field['key'] }}
                                    </code>

                                    @if ($field['help'] !== null)
                                        <p>{{ $field['help'] }}</p>
                                    @endif
                                </div>

                                <button
                                    type="button"
                                    class="supported-field-picker-add"
                                    data-supported-field-picker-add
                                    data-supported-field-key="{{ $field['key'] }}"
                                    wire:click="supportedFieldSelected('{{ $field['key'] }}')"
                                    @click="
                                        open = false;
                                        query = '';
                                    "
                                    aria-label="{{ __('supported_fields.picker.add_field', ['label' => $field['label']]) }}"
                                >
                                    {{ __('supported_fields.picker.add') }}
                                </button>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <p
                class="supported-field-picker-empty"
                x-show="! groupMatches(@js($supportedFieldSearchTerms))"
            >
                {{ __('supported_fields.picker.empty') }}
            </p>
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }

    .supported-field-picker {
        display: grid;
        gap: .55rem;
        margin-bottom: 1rem;
    }

    .supported-field-picker-trigger {
        display: flex;
        width: 100%;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        border: 1px solid rgb(209 213 219);
        border-radius: .6rem;
        padding: .65rem .8rem;
        background: rgb(255 255 255);
        font-weight: 650;
        text-align: left;
    }

    .dark .supported-field-picker-trigger {
        border-color: rgb(75 85 99);
        background: rgb(17 24 39);
    }

    .supported-field-picker-intro {
        margin: 0;
        color: rgb(107 114 128);
        font-size: .8rem;
        line-height: 1.45;
    }

    .supported-field-picker-panel {
        display: grid;
        gap: .9rem;
        border: 1px solid rgb(209 213 219);
        border-radius: .7rem;
        padding: .8rem;
        background: rgb(249 250 251);
    }

    .dark .supported-field-picker-panel {
        border-color: rgb(75 85 99);
        background: rgb(3 7 18);
    }

    .supported-field-picker-header,
    .supported-field-picker-search,
    .supported-field-picker-groups,
    .supported-field-picker-group,
    .supported-field-picker-items {
        display: grid;
        gap: .55rem;
    }

    .supported-field-picker-search {
        font-size: .78rem;
        font-weight: 600;
    }

    .supported-field-picker-search input {
        width: 100%;
        border: 1px solid rgb(209 213 219);
        border-radius: .5rem;
        background: rgb(255 255 255);
        padding: .5rem .6rem;
        font-weight: 400;
    }

    .dark .supported-field-picker-search input {
        border-color: rgb(75 85 99);
        background: rgb(17 24 39);
    }

    .supported-field-picker-group h3 {
        margin: .15rem 0 0;
        font-size: .82rem;
        font-weight: 700;
        letter-spacing: .02em;
    }

    .supported-field-picker-item {
        display: flex;
        align-items: start;
        justify-content: space-between;
        gap: .75rem;
        border: 1px solid rgb(229 231 235);
        border-radius: .55rem;
        padding: .65rem;
        background: rgb(255 255 255);
    }

    .dark .supported-field-picker-item {
        border-color: rgb(55 65 81);
        background: rgb(17 24 39);
    }

    .supported-field-picker-item-event-context {
        border-style: dashed;
        border-width: 2px;
    }

    .supported-field-picker-item-main {
        display: grid;
        min-width: 0;
        gap: .25rem;
    }

    .supported-field-picker-item-title-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .35rem;
    }

    .supported-field-picker-item code {
        width: fit-content;
        max-width: 100%;
        color: rgb(107 114 128);
        font-size: .72rem;
        overflow-wrap: anywhere;
    }

    .supported-field-picker-item p {
        margin: .15rem 0 0;
        color: rgb(75 85 99);
        font-size: .76rem;
        line-height: 1.4;
    }

    .dark .supported-field-picker-item p {
        color: rgb(156 163 175);
    }

    .supported-field-picker-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: .1rem .4rem;
        background: rgb(243 244 246);
        font-size: .66rem;
        font-weight: 650;
        white-space: nowrap;
    }

    .dark .supported-field-picker-badge {
        background: rgb(55 65 81);
    }

    .supported-field-picker-badge-event {
        border: 1px solid currentColor;
        background: transparent;
    }

    .supported-field-picker-add {
        flex: 0 0 auto;
        border: 1px solid rgb(209 213 219);
        border-radius: .45rem;
        padding: .35rem .55rem;
        font-size: .75rem;
        font-weight: 650;
    }

    .dark .supported-field-picker-add {
        border-color: rgb(75 85 99);
    }

    .supported-field-picker-empty {
        margin: 0;
        padding: .7rem;
        color: rgb(107 114 128);
        font-size: .78rem;
        text-align: center;
    }
</style>
